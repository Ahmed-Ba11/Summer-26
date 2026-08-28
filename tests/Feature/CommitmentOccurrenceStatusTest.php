<?php

namespace Tests\Feature;

use App\Models\Commitment;
use App\Models\User;
use App\Services\CommitmentService;
use App\Services\SalaryMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * حالة «الظهور» لا حالة «الالتزام».
 *
 * اشتراك النت يوم 25 يظهر في كل فترة راتب، ولكل ظهور حالته: مسدَّد في
 * أغسطس وقادم في سبتمبر. الحالة تُقرأ من `commitment_payments` بمفتاح
 * الفترة، لا من تاريخ الاستحقاق وحده.
 *
 * كل المبالغ بالهللات: 80 ر.س = 8,000.
 */
class CommitmentOccurrenceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function internet(User $user): Commitment
    {
        return $user->commitments()->create([
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
            'due_day' => 25,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ]);
    }

    /** الظهور في التقويم لشهر معيّن. */
    private function calendarEvent(User $user, string $month, string $label): ?array
    {
        $found = null;

        $this->actingAs($user)
            ->get(route('calendar', ['month' => $month]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$found, $label) {
                $found = collect($page->toArray()['props']['events'])
                    ->first(fn (array $e): bool => $e['label'] === $label);
            });

        return $found;
    }

    public function test_a_commitment_paid_in_august_is_paid_in_august_and_upcoming_in_september(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);

        // يوم 25 مع راتب يوم 27: استحقاق فترة يوليو يقع 25 أغسطس،
        // واستحقاق فترة أغسطس يقع 25 سبتمبر.
        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 8_000])
            ->assertRedirect();

        $august = $this->calendarEvent($user, '2026-08', 'نت');
        $september = $this->calendarEvent($user, '2026-09', 'نت');

        $this->assertNotNull($august, 'ظهور أغسطس غائب عن التقويم.');
        $this->assertNotNull($september, 'ظهور سبتمبر غائب عن التقويم.');

        $this->assertSame('2026-08-25', $august['date']);
        $this->assertSame('paid', $august['status'], 'المسدَّد في أغسطس يظهر مسدَّداً.');
        $this->assertTrue($august['isPaid']);

        $this->assertSame('2026-09-25', $september['date']);
        $this->assertSame('upcoming', $september['status'], 'ظهور سبتمبر قادم لا مسدَّد.');
        $this->assertFalse($september['isPaid']);
    }

    public function test_the_three_statuses_come_from_the_payments_table(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);
        $service = CommitmentService::for($user);
        $salaryMonth = SalaryMonthService::for($user);

        // قادم: استحقاق 25 أغسطس ولمّا يُسدَّد، واليوم 20 أغسطس
        $this->assertSame(
            CommitmentService::STATUS_UPCOMING,
            $service->statusFor($commitment, $salaryMonth->period('2026-07')),
        );

        // متأخّر: نتجاوز تاريخ الاستحقاق بلا سداد
        $this->travelTo('2026-08-28');
        $service->forgetPayments();
        $this->assertSame(
            CommitmentService::STATUS_OVERDUE,
            $service->statusFor($commitment, $salaryMonth->period('2026-07')),
        );

        // مسدَّد: صفّ في commitment_payments لفترة 2026-07
        $service->recordPayment(
            $commitment,
            $service->occurrence($commitment, $salaryMonth->period('2026-07')),
            8_000,
            '2026-08-25',
        );
        $service->forgetPayments();

        $this->assertSame(
            CommitmentService::STATUS_PAID,
            $service->statusFor($commitment, $salaryMonth->period('2026-07')),
        );
    }

    public function test_commitments_page_and_calendar_report_the_same_status(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);

        $this->actingAs($user)->post(route('commitments.pay', $commitment), ['amount' => 8_000]);

        $pageStatus = null;
        $this->actingAs($user)
            ->get(route('commitments'))
            ->assertInertia(function (Assert $page) use (&$pageStatus) {
                $pageStatus = $page->toArray()['props']['commitments'][0]['status'];
            });

        $this->assertSame('paid', $pageStatus, 'صفحة الالتزامات تقرأ الحالة من الخادم.');
        $this->assertSame(
            $pageStatus,
            $this->calendarEvent($user, '2026-08', 'نت')['status'],
            'الصفحتان تقرآن حالة الظهور من نفس الجدول.',
        );
    }

    public function test_dashboard_drops_a_paid_commitment_from_upcoming(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);

        $before = null;
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(function (Assert $page) use (&$before) {
            $before = collect($page->toArray()['props']['calendarEvents'] ?? [])
                ->pluck('label')->all();
        });

        $this->assertContains('نت', $before, 'غير المسدَّد يظهر في قادم اللوحة.');

        $this->actingAs($user)->post(route('commitments.pay', $commitment), ['amount' => 8_000]);

        $after = null;
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(function (Assert $page) use (&$after) {
            $after = collect($page->toArray()['props']['calendarEvents'] ?? [])
                ->pluck('label')->all();
        });

        $this->assertNotContains('نت', $after, 'المسدَّد يخرج من قادم اللوحة.');
    }

    public function test_an_expense_linked_to_a_commitment_writes_a_payment_for_its_period(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);
        $category = $user->categories()->firstOrFail();
        // دخل مسجَّل، وإلا رفض ExpenseFundingService المصروف لتجاوزه المتبقي
        $user->incomes()->create([
            'amount' => 900_000,
            'source' => 'الراتب',
            'income_date' => '2026-07-27',
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => '80',
            'category_id' => $category->id,
            'commitment_id' => $commitment->id,
            'expense_date' => '2026-08-25',
            'description' => 'اشتراك النت',
        ])->assertValid()->assertRedirect();

        // 25 أغسطس داخل فترة راتب يوليو (27 يوليو ← 26 أغسطس)
        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'period_key' => '2026-07',
            'amount' => 8_000,
            'paid_at' => '2026-08-25 00:00:00',
        ]);

        $this->assertSame(
            'paid',
            CommitmentService::for($user)->statusFor(
                $commitment,
                SalaryMonthService::for($user)->period('2026-07'),
            ),
        );
    }

    public function test_deleting_that_expense_takes_its_payment_with_it(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);
        $category = $user->categories()->firstOrFail();
        // دخل مسجَّل، وإلا رفض ExpenseFundingService المصروف لتجاوزه المتبقي
        $user->incomes()->create([
            'amount' => 900_000,
            'source' => 'الراتب',
            'income_date' => '2026-07-27',
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => '80',
            'category_id' => $category->id,
            'commitment_id' => $commitment->id,
            'expense_date' => '2026-08-25',
        ]);

        $this->assertDatabaseCount('commitment_payments', 1);

        $expense = $user->expenses()->firstOrFail();
        $this->actingAs($user)->delete(route('expenses.destroy', $expense))->assertRedirect();

        $this->assertDatabaseCount('commitment_payments', 0);
        // اليوم 20 أغسطس والاستحقاق 25 — يرجع «قادم» لا «متأخّر»
        $this->assertSame(
            'upcoming',
            CommitmentService::for($user)->statusFor(
                $commitment,
                SalaryMonthService::for($user)->period('2026-07'),
            ),
        );
    }

    public function test_paying_from_the_calendar_marks_only_that_occurrence(): void
    {
        $this->travelTo('2026-08-20');

        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->internet($user);

        // نفس النداء الذي ترسله بطاقة اليوم في التقويم
        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 8_000])
            ->assertRedirect();

        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'period_key' => '2026-07',
        ]);

        $this->assertSame('paid', $this->calendarEvent($user, '2026-08', 'نت')['status']);
        $this->assertSame('upcoming', $this->calendarEvent($user, '2026-09', 'نت')['status']);
    }

    public function test_an_expense_cannot_be_linked_to_someone_elses_commitment(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $foreign = $this->internet(User::factory()->create());

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => '80',
            'category_id' => $user->categories()->firstOrFail()->id,
            'commitment_id' => $foreign->id,
            'expense_date' => '2026-08-25',
        ])->assertInvalid('commitment_id');

        $this->assertDatabaseCount('commitment_payments', 0);
    }
}
