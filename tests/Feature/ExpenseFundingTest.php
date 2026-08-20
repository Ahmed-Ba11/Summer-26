<?php

namespace Tests\Feature;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseFundingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * مستخدم بدخل 1,000 ر.س هذا الشهر ولا التزامات —
     * المتاح للصرف 100,000 هللة بالضبط.
     */
    private function userWithIncome(): array
    {
        $user = User::factory()->create();
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);

        return [$user, $user->categories()->firstOrFail()];
    }

    public function test_expense_exceeding_available_without_funding_source_is_rejected(): void
    {
        [$user, $category] = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
            ])
            ->assertInvalid('funding_source');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_expense_funded_from_savings_reduces_the_goal_by_the_exact_shortfall(): void
    {
        [$user, $category] = $this->userWithIncome();
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف',
            'target_amount' => 200_000,
            'current_amount' => 50_000,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'savings',
                'savings_goal_id' => $goal->id,
            ])
            ->assertRedirect();

        // العجز = 110,000 − 100,000 = 10,000 هللة بالضبط
        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => 40_000,
        ]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 110_000,
            'funding_source' => 'savings',
        ]);
    }

    public function test_expense_funded_from_savings_with_insufficient_goal_balance_is_rejected(): void
    {
        [$user, $category] = $this->userWithIncome();
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف صغير',
            'target_amount' => 200_000,
            'current_amount' => 5_000,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'savings',
                'savings_goal_id' => $goal->id,
            ])
            ->assertInvalid('savings_goal_id');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => 5_000,
        ]);
    }

    public function test_expense_funded_by_unlogged_income_creates_an_income_record(): void
    {
        [$user, $category] = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'unlogged_income',
                'income_amount' => 10_000,
                'income_source' => 'عمل حر',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'amount' => 10_000,
            'source' => 'عمل حر',
        ]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 110_000,
            'funding_source' => 'unlogged_income',
        ]);
    }

    public function test_expense_funded_by_unlogged_income_below_the_shortfall_is_rejected(): void
    {
        [$user, $category] = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'unlogged_income',
                'income_amount' => 5_000,
            ])
            ->assertInvalid('income_amount');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('incomes', 1);
    }

    public function test_expense_marked_as_overspend_is_saved_with_the_marker(): void
    {
        [$user, $category] = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'overspend',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 110_000,
            'funding_source' => 'overspend',
        ]);
    }

    public function test_expense_within_the_available_balance_is_saved_without_funding_source(): void
    {
        [$user, $category] = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '500.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 50_000,
            'funding_source' => null,
        ]);
    }

    public function test_funding_cannot_use_another_users_savings_goal(): void
    {
        [$user, $category] = $this->userWithIncome();
        $otherUser = User::factory()->create();
        $otherGoal = SavingsGoal::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'هدف مستخدم آخر',
            'target_amount' => 200_000,
            'current_amount' => 100_000,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '1100.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'funding_source' => 'savings',
                'savings_goal_id' => $otherGoal->id,
            ])
            ->assertInvalid('savings_goal_id');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('savings_goals', [
            'id' => $otherGoal->id,
            'current_amount' => 100_000,
        ]);
    }
}
