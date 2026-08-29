<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\User;
use App\Services\CommitmentService;
use App\Services\SalaryMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ليس كل التزام شهرياً.
 *
 * اشتراك إنترنت لشهر واحد ثم يُلغى كان يظهر في كل فترة راتب بعده، لأن
 * مولّد الظهورات يفترض التكرار في كل التزام. والالتزام الذي أُلغي لم يكن
 * أمام المستخدم إلا حذفه — فيضيع معه سجل الأشهر التي دفعها فعلاً.
 *
 * كل المبالغ بالهللات: 80 ر.س = 8,000.
 */
class CommitmentRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function commitment(User $user, array $overrides = []): Commitment
    {
        return $user->commitments()->create(array_merge([
            'kind' => 'subscription',
            'name' => 'نت',
            'icon' => 'wifi',
            'amount' => 8_000,
            'is_variable' => false,
            'total_amount' => 0,
            'months_count' => 0,
            'months_paid' => 0,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'recurrence' => 'monthly',
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ], $overrides));
    }

    /** `created_at` ليس في `$fillable` — والظهورات تُقاس منه. */
    private function backdate(Commitment $commitment, CarbonImmutable $moment): Commitment
    {
        $commitment->forceFill(['created_at' => $moment])->save();

        return $commitment->fresh();
    }

    /** الفترات الثلاث: السابقة · الحالية · التالية. */
    private function windowKeys(User $user): array
    {
        $salaryMonth = SalaryMonthService::for($user);

        return [
            $salaryMonth->previous(),
            $salaryMonth->current(),
            $salaryMonth->next(),
        ];
    }

    public function test_a_one_off_commitment_appears_in_its_own_period_only(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $periods = $this->windowKeys($user);
        $dueOn = $periods[1]['startsOn']->addDays(2);

        $commitment = $this->commitment($user, [
            'recurrence' => 'once',
            'due_day' => null,
            'due_on' => $dueOn->toDateString(),
        ]);

        $service = CommitmentService::for($user);
        $occurrences = $service->occurrences($commitment, $periods);

        $this->assertCount(1, $occurrences);
        $this->assertSame($dueOn->toDateString(), $occurrences[0]['due_date']);
    }

    public function test_a_one_off_commitment_is_reserved_in_its_period_and_nowhere_else(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $periods = $this->windowKeys($user);

        $this->commitment($user, [
            'recurrence' => 'once',
            'due_day' => null,
            'due_on' => $periods[1]['startsOn']->addDays(2)->toDateString(),
        ]);

        $service = CommitmentService::for($user);

        $this->assertSame(8_000, $service->reservedForPeriod($periods[1]));
        $service->forgetPayments();
        $this->assertSame(0, $service->reservedForPeriod($periods[2]));
    }

    public function test_stopping_a_recurring_commitment_ends_its_occurrences_but_keeps_the_past(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $periods = $this->windowKeys($user);

        // يُوقف من بداية الفترة الحالية: ما قبلها يبقى، وما بعدها لا يوجد.
        $commitment = $this->backdate(
            $this->commitment($user, [
                'ends_on' => $periods[1]['startsOn']->toDateString(),
            ]),
            $periods[0]['startsOn'],
        );

        $service = CommitmentService::for($user);
        $occurrences = $service->occurrences($commitment, $periods);

        $this->assertCount(1, $occurrences);
        $this->assertSame($periods[0]['key'], $occurrences[0]['period_key']);
        $this->assertTrue($service->isStopped($commitment));
    }

    public function test_a_stopped_commitment_is_not_reserved_from_the_budget(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $periods = $this->windowKeys($user);

        $this->backdate(
            $this->commitment($user, [
                'ends_on' => $periods[1]['startsOn']->toDateString(),
            ]),
            $periods[0]['startsOn'],
        );

        $service = CommitmentService::for($user);

        $this->assertSame(0, $service->reservedForPeriod($periods[1]));
        $service->forgetPayments();
        $this->assertSame(0, $service->obligationsForPeriod($periods[1]));
    }

    public function test_a_stopped_commitment_cannot_be_paid(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user, [
            'ends_on' => CarbonImmutable::today()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 8_000])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('commitment_payments', 0);
    }

    public function test_the_form_stores_a_one_off_commitment_with_its_single_date(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'subscription',
                'name' => 'اشتراك شهر',
                'amount' => 8_000,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
                'recurrence' => 'once',
                'due_on' => '2026-09-10',
                'notify_when' => 'before_3',
                'reserve_in_budget' => true,
            ])
            ->assertRedirect();

        $commitment = $user->commitments()->firstOrFail();

        $this->assertSame('once', $commitment->recurrence);
        $this->assertSame('2026-09-10', $commitment->due_on->toDateString());
        // غير المتكرّر لا يوم شهري له — بقاؤه يجعل مصدرين للتاريخ.
        $this->assertNull($commitment->due_day);
        $this->assertNull($commitment->ends_on);
    }

    public function test_a_one_off_commitment_without_a_date_is_refused(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'bill',
                'name' => 'فاتورة',
                'amount' => 8_000,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
                'recurrence' => 'once',
                'notify_when' => 'before_3',
                'reserve_in_budget' => true,
            ])
            ->assertSessionHasErrors('due_on');

        $this->assertDatabaseCount('commitments', 0);
    }

    public function test_an_installment_cannot_be_made_one_off(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $user->incomes()->create([
            'amount' => 1_000_000,
            'source' => 'راتب',
            'income_date' => now(),
            'is_recurring' => true,
        ]);

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'installment',
                'name' => 'قسط',
                'amount' => 8_000,
                'monthly_amount' => 8_000,
                'total_amount' => 96_000,
                'months_count' => 12,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
                'recurrence' => 'once',
                'due_on' => '2026-09-10',
                'notify_when' => 'before_3',
                'reserve_in_budget' => true,
            ])
            ->assertSessionHasErrors('recurrence');
    }

    public function test_editing_a_commitment_can_stop_it_from_a_date_instead_of_deleting_it(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user);

        $this->actingAs($user)
            ->put(route('commitments.update', $commitment), [
                'kind' => 'subscription',
                'name' => 'نت',
                'amount' => 8_000,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
                'recurrence' => 'monthly',
                'ends_on' => '2026-10-01',
                'notify_when' => 'before_3',
                'reserve_in_budget' => true,
            ])
            ->assertRedirect();

        $commitment->refresh();

        $this->assertSame('2026-10-01', $commitment->ends_on->toDateString());
        // الإيقاف ليس حذفاً: الالتزام يبقى قائماً وسجلّه معه.
        $this->assertTrue((bool) $commitment->is_active);
    }

    public function test_clearing_the_stop_date_brings_the_commitment_back(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user, [
            'ends_on' => CarbonImmutable::today()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->put(route('commitments.update', $commitment), [
                'kind' => 'subscription',
                'name' => 'نت',
                'amount' => 8_000,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
                'recurrence' => 'monthly',
                'ends_on' => null,
                'notify_when' => 'before_3',
                'reserve_in_budget' => true,
            ])
            ->assertRedirect();

        $this->assertNull($commitment->fresh()->ends_on);
        $this->assertFalse(CommitmentService::for($user)->isStopped($commitment->fresh()));
    }

    public function test_existing_commitments_stay_monthly_after_the_migration(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->backdate(
            $this->commitment($user),
            CarbonImmutable::today()->subMonths(3),
        );

        $this->assertSame('monthly', $commitment->recurrence);
        $this->assertCount(3, CommitmentService::for($user)
            ->occurrences($commitment, $this->windowKeys($user)));
    }
}
