<?php

namespace Tests\Feature;

use App\Models\SavingsDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * تعديل إيداع الادخار وحذفه.
 *
 * كان الإيداع الخاطئ يبقى خاطئاً إلى الأبد: لا مسار تعديل ولا حذف،
 * والمخرج الوحيد حذف الهدف كلّه ومعه تاريخه.
 *
 * كل المبالغ بالهللات: 150 ر.س = 15,000.
 */
class SavingsDepositEditTest extends TestCase
{
    use RefreshDatabase;

    private function goalWithDeposit(User $user, string $amount = '150.00')
    {
        $goal = $user->savingsGoals()->create([
            'name' => 'سيارة',
            'target_amount' => 200_000,
            'current_amount' => 0,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->put(route('savings.update', $goal), ['amount' => $amount])
            ->assertRedirect();

        return $goal->fresh();
    }

    public function test_amending_a_deposit_moves_the_balance_to_the_new_amount(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $goal = $this->goalWithDeposit($user);
        $deposit = SavingsDeposit::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('savings.deposits.update', $deposit), ['amount' => '90.00'])
            ->assertRedirect();

        $this->assertSame(9_000, (int) $deposit->fresh()->amount);
        // الرصيد يُعاد جمعه من الحركات — لا يُزاد بالفرق فيتراكم انحراف.
        $this->assertSame(9_000, (int) $goal->fresh()->current_amount);
    }

    public function test_deleting_a_deposit_returns_the_balance_to_what_it_was(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $goal = $this->goalWithDeposit($user);
        $deposit = SavingsDeposit::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('savings.deposits.destroy', $deposit))
            ->assertRedirect();

        $this->assertDatabaseMissing('savings_deposits', ['id' => $deposit->id]);
        $this->assertSame(0, (int) $goal->fresh()->current_amount);
    }

    public function test_amending_below_the_target_takes_the_completion_flag_back(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $goal = $user->savingsGoals()->create([
            'name' => 'طوارئ',
            'target_amount' => 10_000,
            'current_amount' => 0,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->put(route('savings.update', $goal), ['amount' => '100.00'])
            ->assertRedirect();
        $this->assertTrue((bool) $goal->fresh()->is_completed);

        $deposit = SavingsDeposit::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('savings.deposits.update', $deposit), ['amount' => '20.00'])
            ->assertRedirect();

        $this->assertFalse((bool) $goal->fresh()->is_completed);
        $this->assertSame(2_000, (int) $goal->fresh()->current_amount);
    }

    public function test_a_deposit_of_another_user_cannot_be_touched(): void
    {
        $owner = User::factory()->create(['salary_day' => 27]);
        $this->goalWithDeposit($owner);
        $deposit = SavingsDeposit::query()->firstOrFail();

        $intruder = User::factory()->create(['salary_day' => 27]);

        $this->actingAs($intruder)
            ->put(route('savings.deposits.update', $deposit), ['amount' => '1.00'])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('savings.deposits.destroy', $deposit))
            ->assertForbidden();

        $this->assertSame(15_000, (int) $deposit->fresh()->amount);
    }

    public function test_the_savings_page_ships_each_goal_ledger_so_it_can_be_corrected(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $this->goalWithDeposit($user);

        $this->actingAs($user)
            ->get(route('savings'))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $deposits = $page->toArray()['props']['goals'][0]['deposits'];

                $this->assertCount(1, $deposits);
                $this->assertSame(15_000, $deposits[0]['amount']);
            });
    }
}
