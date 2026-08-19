<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetGuardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_is_rejected_when_total_exceeds_available_capacity(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'category_id' => $category->id,
                'amount' => '101.00',
                'month' => now()->format('Y-m'),
            ])
            ->assertInvalid('amount');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_savings_goal_is_rejected_when_required_monthly_saving_exceeds_capacity(): void
    {
        $user = User::factory()->create();
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('savings.store'), [
                'name' => 'سيارة',
                'target_amount' => '500.00',
                'target_date' => now()->addMonth()->toDateString(),
            ])
            ->assertInvalid('target_amount');

        $this->assertDatabaseCount('savings_goals', 0);
    }

    public function test_installment_is_rejected_when_commitments_exceed_income(): void
    {
        $user = User::factory()->create();
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('installments.store'), [
                'name' => 'قسط',
                'monthly_amount' => '101.00',
                'total_amount' => '1212.00',
                'total_months' => 12,
                'start_date' => now()->format('Y-m'),
            ])
            ->assertInvalid('monthly_amount');

        $this->assertDatabaseCount('installments', 0);
    }

    public function test_bill_is_rejected_when_commitments_exceed_income(): void
    {
        $user = User::factory()->create();
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('bills.store'), [
                'name' => 'فاتورة',
                'amount' => '101.00',
                'due_date' => now()->toDateString(),
            ])
            ->assertInvalid('amount');

        $this->assertDatabaseCount('bills', 0);
    }

    public function test_expense_is_saved_with_category_budget_warning(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);
        $user->budgets()->create([
            'category_id' => $category->id,
            'amount' => 10_000,
            'month' => now()->format('Y-m'),
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '150.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('warnings.0.severity', 'warn');

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 15_000,
        ]);
    }

    public function test_expense_is_saved_with_negative_available_balance_warning(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->startOfMonth(),
        ]);
        $user->bills()->create([
            'name' => 'فاتورة',
            'amount' => 9_000,
            'due_date' => now()->toDateString(),
            'is_paid' => false,
        ]);

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '20.00',
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('warnings.0.severity', 'danger');

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 2_000,
        ]);
    }
}
