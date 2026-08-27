<?php

namespace Tests\Feature;

use App\Models\SalaryPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_creates_a_ledger_row_and_updates_the_balance_together(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف',
            'target_amount' => 200_000,
            'current_amount' => 0,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->put(route('savings.update', $goal), ['amount' => '150.00'])
            ->assertRedirect();

        $this->assertSame(15_000, (int) $goal->fresh()->current_amount);
        $this->assertDatabaseHas('savings_deposits', [
            'user_id' => $user->id,
            'savings_goal_id' => $goal->id,
            'amount' => 15_000,
            'period_key' => SalaryPeriod::keyFor($user),
        ]);
    }

    public function test_withdrawal_is_recorded_as_a_negative_row_so_the_monthly_net_stays_true(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'راتب',
            'income_date' => now(),
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف',
            'target_amount' => 200_000,
            'current_amount' => 50_000,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => '1100.00',
            'category_id' => $user->categories()->firstOrFail()->id,
            'expense_date' => now()->toDateString(),
            'funding_source' => 'savings',
            'savings_goal_id' => $goal->id,
        ]);

        $this->assertSame(40_000, (int) $goal->fresh()->current_amount);
        $this->assertDatabaseHas('savings_deposits', [
            'savings_goal_id' => $goal->id,
            'amount' => -10_000,
        ]);
        $this->assertSame(-10_000, (int) $user->savingsDeposits()->sum('amount'));
    }

    public function test_the_salary_period_key_starts_at_the_salary_day(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);

        $this->assertSame('2026-08', SalaryPeriod::keyFor($user, '2026-08-27'));
        $this->assertSame('2026-07', SalaryPeriod::keyFor($user, '2026-08-26'));

        $bounds = SalaryPeriod::boundsFor($user, '2026-08');
        $this->assertSame('2026-08-27', $bounds['starts_on']->toDateString());
        $this->assertSame('2026-09-26', $bounds['ends_on']->toDateString());
    }
}
