<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «مكتمل» واقعة يقرّرها الرصيد، لا راية يكتبها أي مسار يشاء.
 *
 * كل المبالغ هنا بالهللات: 30,000 ر.س = 3,000,000 · 2,000 ر.س = 200,000.
 */
class SavingsCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_thousand_of_thirty_thousand_is_not_complete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('savings.store'), [
            'name' => 'سيارة',
            'target_amount' => '30000',
        ])->assertRedirect();

        $goal = $user->savingsGoals()->firstOrFail();

        // الوحدة المخزّنة: الهدف بالهللات لا بالريالات
        $this->assertSame(3_000_000, (int) $goal->target_amount);

        $this->actingAs($user)->put(route('savings.update', $goal), ['amount' => '2000']);

        $goal->refresh();

        $this->assertSame(200_000, (int) $goal->current_amount);
        $this->assertSame(200_000, (int) $user->savingsDeposits()->sum('amount'));
        $this->assertFalse($goal->is_completed, '2,000 ر.س من 30,000 ر.س ليست هدفاً مكتملاً.');
        $this->assertFalse($goal->hasReachedTarget());
    }

    public function test_closing_a_goal_early_does_not_mark_it_complete(): void
    {
        $user = User::factory()->create();
        $goal = $user->savingsGoals()->create([
            'name' => 'سيارة',
            'target_amount' => 3_000_000,
            'current_amount' => 0,
        ]);

        SavingsLedger::for($user)->deposit($goal, 200_000);

        $this->actingAs($user)->put(route('savings.complete', $goal))->assertRedirect();

        $goal->refresh();

        $this->assertTrue($goal->is_closed, 'الإقفال قرار المستخدم ويُنفَّذ.');
        $this->assertFalse($goal->is_completed, 'الإقفال المبكر لا يجعل الهدف مكتملاً.');
    }

    public function test_reaching_the_target_marks_the_goal_complete(): void
    {
        $user = User::factory()->create();
        $goal = $user->savingsGoals()->create([
            'name' => 'سيارة',
            'target_amount' => 3_000_000,
            'current_amount' => 0,
        ]);

        SavingsLedger::for($user)->deposit($goal, 3_000_000);

        $this->assertTrue($goal->fresh()->is_completed);

        // السحب تحت الهدف يرفع الاكتمال — الراية تتبع الرصيد في الاتجاهين
        SavingsLedger::for($user)->withdraw($goal->fresh(), 100_000);

        $this->assertFalse($goal->fresh()->is_completed);
    }

    public function test_a_goal_cannot_be_closed_by_another_user(): void
    {
        $goal = User::factory()->create()->savingsGoals()->create([
            'name' => 'سيارة',
            'target_amount' => 3_000_000,
            'current_amount' => 0,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('savings.complete', $goal))
            ->assertForbidden();

        $this->assertFalse($goal->fresh()->is_closed);
    }
}
