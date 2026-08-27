<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * التقويم يقرأ من `commitments` — المصدر الوحيد منذ ترحيل 2026-08-24.
     * كان يقرأ من `bills` و`installments` القديمين، فكل التزام أُضيف بعد
     * الترحيل يظهر في صفحة الالتزامات ويغيب عن التقويم.
     */
    public function test_authenticated_users_can_view_calendar_events_for_a_month(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);

        $commitment = $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'فاتورة اختبار',
            'icon' => 'receipt',
            'amount' => 15000,
            'is_variable' => false,
            'total_amount' => 0,
            'months_count' => 0,
            'months_paid' => 0,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'notify_when' => 'before_3',
            'reserve_in_budget' => true,
            'is_active' => true,
        ]);

        // نفس التاريخ الذي تعرضه صفحة الالتزامات
        $service = CommitmentService::for($user);
        $dueDate = $service->dueDateFor($commitment, $service->currentPeriod());
        $month = $dueDate->format('Y-m');

        $this->actingAs($user)
            ->get(route('calendar', ['month' => $month]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calendar')
                ->where('month', $month)
                ->where('events.0.kind', 'bill')
                ->where('events.0.label', 'فاتورة اختبار')
                ->where('events.0.date', $dueDate->format('Y-m-d'))
                ->where('events.0.amount', 15000)
                ->where('events.0.canPay', true));
    }

    public function test_guests_are_redirected_from_calendar(): void
    {
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }
}
