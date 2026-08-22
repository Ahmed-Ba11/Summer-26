<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_calendar_events_for_a_month(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $month = now()->format('Y-m');

        $user->bills()->create([
            'name' => 'فاتورة اختبار',
            'amount' => 15000,
            'due_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'is_paid' => false,
        ]);

        $this->actingAs($user)
            ->get(route('calendar', ['month' => $month]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calendar')
                ->where('month', $month)
                ->has('events', 1)
                ->where('events.0.kind', 'bill')
                ->where('events.0.amount', 15000)
                ->where('events.0.canPay', true));
    }

    public function test_guests_are_redirected_from_calendar(): void
    {
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }
}
