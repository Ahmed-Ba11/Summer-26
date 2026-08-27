<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_overage_is_accepted_and_marks_the_goal_completed(): void
    {
        $user = User::factory()->create();
        $goal = $user->savingsGoals()->create([
            'name' => 'رحلة',
            'target_amount' => 10_000,
            'current_amount' => 0,
            'is_completed' => false,
        ]);

        $this->actingAs($user)
            ->put(route('savings.update', $goal), ['amount' => '150.00'])
            ->assertRedirect()
            ->assertSessionHas('warnings.0.severity', 'success')
            ->assertSessionHas('warnings.0.overage', 5_000);

        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => 15_000,
            'is_completed' => true,
        ]);
    }

    public function test_completed_savings_goal_returns_redirected_validation_errors(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::query()->create([
            'user_id' => $user->id,
            'name' => 'هدف مكتمل',
            'target_amount' => 10_000,
            'current_amount' => 10_000,
            'is_completed' => true,
            'is_closed' => true,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put(route('savings.update', $goal), ['amount' => '1.00'])
            ->assertRedirect()
            ->assertSessionHasErrors(['amount' => 'هذا الهدف مكتمل ومغلق بالفعل.']);
    }

    public function test_completed_installment_returns_redirected_validation_errors(): void
    {
        $user = User::factory()->create();
        $installment = $user->installments()->create([
            'name' => 'قسط مكتمل',
            'monthly_amount' => 1_000,
            'total_amount' => 1_000,
            'paid_months' => 1,
            'total_months' => 1,
            'start_date' => now()->format('Y-m'),
            'is_completed' => true,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put(route('installments.pay', $installment))
            ->assertRedirect()
            ->assertSessionHasErrors(['paid_months' => 'هذا القسط مكتمل بالفعل.']);
    }

    public function test_savings_rate_uses_current_month_deposits(): void
    {
        $user = User::factory()->create();
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'راتب',
            'income_date' => now(),
        ]);
        $goal = $user->savingsGoals()->create([
            'name' => 'هدف شهري',
            'target_amount' => 100_000,
            'current_amount' => 50_000,
            'is_completed' => false,
            'is_closed' => false,
        ]);
        SavingsLedger::for($user)->deposit($goal, 5_000);

        $this->actingAs($user)
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->withHeaders(['X-Inertia' => 'true'])
            ->get(route('savings'))
            ->assertOk()
            ->assertJsonPath('props.stats.monthly_deposits', 5_000)
            ->assertJsonPath('props.stats.savings_rate', 5);
    }
}
