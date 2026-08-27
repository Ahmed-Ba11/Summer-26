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
 * كل المبالغ بالهللات: 250 ر.س = 25,000.
 */
class CommitmentEditAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function commitment(User $user, array $overrides = []): Commitment
    {
        return $user->commitments()->create(array_merge([
            'kind' => 'bill',
            'name' => 'كهرباء',
            'icon' => 'zap',
            'amount' => 25_000,
            'is_variable' => false,
            'total_amount' => 0,
            'months_count' => 0,
            'months_paid' => 0,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 10,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_editing_a_commitment_amount_is_actually_saved(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user);

        $this->actingAs($user)->put(route('commitments.update', $commitment), [
            'kind' => 'bill',
            'name' => 'كهرباء',
            'amount' => 41_000,
            'is_variable' => false,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 10,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
        ])->assertRedirect();

        $this->assertSame(41_000, (int) $commitment->fresh()->amount);
    }

    public function test_editing_preserves_the_due_day_and_notification_it_was_opened_with(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user, ['due_day' => 18, 'notify_when' => 'on_due']);

        // القيم التي يعبّئها النموذج من الـprops لازم تصل كما هي
        $props = CommitmentService::for($user)->hydrate(collect([$commitment]));

        $this->assertSame(18, $props[0]['due_day']);
        $this->assertSame('on_due', $props[0]['notify_when']);

        $this->actingAs($user)->put(route('commitments.update', $commitment), [
            'kind' => 'bill',
            'name' => 'كهرباء',
            'amount' => 30_000,
            'is_variable' => false,
            'payment_method' => 'manual',
            'due_type' => $props[0]['due_type'],
            'due_day' => $props[0]['due_day'],
            'notify_when' => $props[0]['notify_when'],
            'reserve_in_budget' => true,
        ])->assertRedirect();

        $fresh = $commitment->fresh();

        $this->assertSame(30_000, (int) $fresh->amount);
        $this->assertSame(18, (int) $fresh->due_day, 'يوم الاستحقاق لا يُداس عند التعديل.');
        $this->assertSame('on_due', $fresh->notify_when);
    }

    public function test_a_commitment_cannot_be_edited_by_another_user(): void
    {
        $commitment = $this->commitment(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->put(route('commitments.update', $commitment), [
                'kind' => 'bill',
                'name' => 'مخترَق',
                'amount' => 1,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 1,
                'notify_when' => 'before_3',
            ])
            // 404 لا 403 — المشروع لا يُقرّ بوجود سجلّ لا يملكه الطالب
            ->assertNotFound();

        $this->assertSame('كهرباء', $commitment->fresh()->name);
    }

    public function test_every_commitment_due_in_the_period_appears_in_the_calendar(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);

        $monthDay = $this->commitment($user, ['name' => 'كهرباء', 'due_day' => 10]);
        $salaryDay = $this->commitment($user, [
            'name' => 'إيجار',
            'kind' => 'rent',
            'due_type' => 'salary_day',
            'due_day' => null,
            'amount' => 200_000,
        ]);

        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();

        // التاريخ الذي تعرضه صفحة الالتزامات
        $expected = [
            $monthDay->name => $service->dueDateFor($monthDay, $period)->format('Y-m-d'),
            $salaryDay->name => $service->dueDateFor($salaryDay, $period)->format('Y-m-d'),
        ];

        foreach ($expected as $name => $date) {
            $this->actingAs($user)
                ->get(route('calendar', ['month' => substr($date, 0, 7)]))
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($name, $date) {
                    $events = $page->toArray()['props']['events'];
                    $match = collect($events)->first(
                        fn (array $e): bool => $e['label'] === $name && $e['date'] === $date,
                    );

                    $this->assertNotNull(
                        $match,
                        "«{$name}» يستحق {$date} في صفحة الالتزامات ولا يظهر في التقويم.",
                    );
                });
        }
    }

    public function test_calendar_marks_a_paid_commitment_as_paid(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user, ['due_day' => 10]);

        $service = CommitmentService::for($user);
        $period = $service->currentPeriod();
        $dueDate = $service->dueDateFor($commitment, $period);

        $commitment->payments()->create([
            'amount' => 25_000,
            'paid_at' => now()->toDateString(),
            'period_key' => $period['key'],
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->get(route('calendar', ['month' => $dueDate->format('Y-m')]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($commitment) {
                $event = collect($page->toArray()['props']['events'])
                    ->first(fn (array $e): bool => $e['label'] === $commitment->name);

                $this->assertNotNull($event);
                $this->assertTrue($event['isPaid'], 'المدفوع في الالتزامات مدفوع في التقويم.');
            });
    }

    public function test_calendar_and_commitments_page_agree_on_the_same_due_date(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $commitment = $this->commitment($user, ['due_type' => 'salary_day', 'due_day' => null]);

        $pageDate = null;

        $this->actingAs($user)
            ->get(route('commitments'))
            ->assertInertia(function (Assert $page) use (&$pageDate) {
                $pageDate = $page->toArray()['props']['commitments'][0]['due_date'];
            });

        $this->assertNotNull($pageDate);
        $this->assertSame(
            SalaryMonthService::for($user)->current()['salaryDate']->format('Y-m-d'),
            $pageDate,
            'الاستحقاق المربوط بالراتب يُحسب على SalaryMonthService.',
        );

        $this->actingAs($user)
            ->get(route('calendar', ['month' => substr($pageDate, 0, 7)]))
            ->assertInertia(function (Assert $page) use ($commitment, $pageDate) {
                $event = collect($page->toArray()['props']['events'])
                    ->first(fn (array $e): bool => $e['label'] === $commitment->name);

                $this->assertNotNull($event, 'الالتزام غائب عن التقويم.');
                $this->assertSame($pageDate, $event['date'], 'الصفحتان تقرآن من نفس المصدر.');
            });
    }
}
