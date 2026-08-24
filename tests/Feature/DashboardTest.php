<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_navigation_shared_data_and_temporary_redirects_are_available(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $category = $user->categories()->firstOrFail();
        $month = now()->format('Y-m');

        $user->incomes()->create([
            'amount' => 800000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth()->toDateString(),
        ]);
        $user->expenses()->create([
            'category_id' => $category->id,
            'amount' => 10000,
            'description' => 'مصروف اختباري',
            'expense_date' => now()->toDateString(),
        ]);
        $user->budgets()->create([
            'category_id' => $category->id,
            'amount' => 50000,
            'month' => $month,
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف اختباري',
            'target_amount' => 100000,
            'current_amount' => 0,
            'target_date' => null,
            'is_completed' => false,
            'is_closed' => false,
        ]);
        $user->savingsDeposits()->create([
            'savings_goal_id' => $goal->id,
            'amount' => 40000,
            'deposited_at' => now()->toDateString(),
        ]);
        $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'فاتورة اختباريّة',
            'amount' => 100000,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => now()->addDay()->day,
            'reserve_in_budget' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withCookie('rail_expanded', '1')
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('railExpanded', true)
                ->where('navStats.remaining', 690000)
                ->where('navStats.budgetUsedPct', 20)
                ->where('navStats.transactionsCount', 2)
                ->where('navStats.savingsPct', 5)
                ->has('navStats.incomeSplit', 4));

        $this->actingAs($user)
            ->get('/transactions')
            ->assertRedirect('/expenses');
        $this->actingAs($user)
            ->get('/commitments')
            ->assertOk();
    }
}
