<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\User;
use App\Services\SalaryMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackendFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_owned_default_categories(): void
    {
        $user = User::factory()->create();

        $this->assertCount(7, $user->categories);
        $this->assertTrue($user->categories->every(fn (Category $category): bool => $category->user_id === $user->id));
    }

    public function test_expenses_and_budgets_reject_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = $otherUser->categories()->first();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => 10,
                'category_id' => $otherCategory->id,
                'description' => 'اختبار',
                'expense_date' => '2026-08-19',
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'amount' => 10,
                'category_id' => $otherCategory->id,
                'month' => '2026-08',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('expenses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('budgets', ['user_id' => $user->id]);

        $expense = $user->expenses()->create([
            'category_id' => $user->categories()->first()->id,
            'amount' => 1000,
            'description' => 'مصروف',
            'expense_date' => '2026-08-19',
        ]);
        $this->actingAs($user)
            ->put(route('expenses.update', $expense), [
                'amount' => 10,
                'category_id' => $otherCategory->id,
                'description' => 'تعديل',
                'expense_date' => '2026-08-19',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_expense_and_income_recurring_flags_are_persisted_on_create_and_update(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->first();
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'دخل اختباري',
            'income_date' => now(),
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => 12.50,
            'category_id' => $category->id,
            'description' => 'مصروف متكرر',
            'expense_date' => '2026-08-19',
            'is_recurring' => true,
        ]);
        $expense = $user->expenses()->firstOrFail();
        $this->assertTrue($expense->is_recurring);

        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'amount' => 12.50,
            'category_id' => $category->id,
            'description' => 'مصروف عادي',
            'expense_date' => '2026-08-19',
            'is_recurring' => false,
        ]);
        $this->assertFalse($expense->refresh()->is_recurring);

        $this->actingAs($user)->post(route('income.store'), [
            'amount' => 100,
            'source' => 'راتب',
            'description' => 'دخل متكرر',
            'income_date' => '2026-08-01',
            'is_recurring' => true,
        ]);
        $income = $user->incomes()->latest('id')->firstOrFail();
        $this->assertTrue($income->is_recurring);

        $this->actingAs($user)->put(route('income.update', $income), [
            'amount' => 100,
            'source' => 'راتب',
            'description' => 'دخل عادي',
            'income_date' => '2026-08-01',
            'is_recurring' => false,
        ]);
        $this->assertFalse($income->refresh()->is_recurring);
        $this->assertSame(1250, $expense->amount);
        $this->assertSame(10000, $income->amount);
    }

    public function test_bills_can_be_created_updated_paid_unpaid_and_deleted_only_by_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $bill = $otherUser->bills()->create([
            'name' => 'فاتورة أخرى',
            'amount' => 1000,
            'due_date' => '2026-08-20',
            'is_paid' => false,
        ]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), [
                'name' => 'فاتورة أخرى',
                'amount' => 20,
                'due_date' => '2026-08-20',
            ])
            ->assertForbidden();

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'فاتورة كهرباء',
            'amount' => 35.50,
            'due_date' => '2026-08-20',
            'account_number' => '12345',
        ])->assertRedirect();

        $bill = $user->bills()->firstOrFail();
        $this->assertSame(3550, $bill->amount);

        $this->actingAs($user)->put(route('bills.update', $bill), [
            'name' => 'فاتورة محدثة',
            'amount' => 40,
            'due_date' => '2026-08-21',
            'account_number' => '67890',
        ])->assertRedirect();
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'name' => 'فاتورة محدثة',
            'amount' => 4000,
            'is_paid' => false,
        ]);

        $this->actingAs($user)->put(route('bills.pay', $bill))->assertRedirect();
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'is_paid' => true]);
        $this->actingAs($user)->put(route('bills.unpay', $bill))->assertRedirect();
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'is_paid' => false]);

        $this->actingAs($user)->delete(route('bills.destroy', $bill))->assertRedirect();
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    }

    public function test_installment_payments_stop_at_total_months_and_enforce_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post(route('installments.store'), [
            'name' => 'قسط جهاز',
            'monthly_amount' => 100,
            'total_amount' => 200,
            'total_months' => 2,
            'start_date' => '2026-08',
        ]);
        $installment = $user->installments()->firstOrFail();

        $this->actingAs($user)->put(route('installments.pay', $installment))->assertRedirect();
        $this->actingAs($user)->put(route('installments.pay', $installment))->assertRedirect();
        $this->assertDatabaseHas('installments', [
            'id' => $installment->id,
            'paid_months' => 2,
            'is_completed' => true,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put(route('installments.pay', $installment))
            ->assertRedirect()
            ->assertSessionHasErrors('paid_months');
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_months' => 2]);

        $otherInstallment = $otherUser->installments()->create([
            'name' => 'قسط آخر',
            'monthly_amount' => 1000,
            'total_amount' => 2000,
            'paid_months' => 0,
            'total_months' => 2,
            'start_date' => '2026-08',
            'is_completed' => false,
        ]);
        $this->actingAs($user)
            ->put(route('installments.pay', $otherInstallment))
            ->assertForbidden();
    }

    public function test_savings_additions_accept_overage_but_stop_after_goal_completion(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post(route('savings.store'), [
            'name' => 'رحلة',
            'target_amount' => 100,
        ]);
        $goal = $user->savingsGoals()->firstOrFail();

        $this->actingAs($user)->put(route('savings.update', $goal), ['amount' => 60])->assertRedirect();
        $this->actingAs($user)
            ->put(route('savings.update', $goal), ['amount' => 50])
            ->assertRedirect()
            ->assertSessionHas('warnings.0.overage', 1000);
        $this->assertDatabaseHas('savings_goals', [
            'id' => $goal->id,
            'current_amount' => 11000,
            'is_completed' => true,
        ]);
        $this->actingAs($user)->put(route('savings.complete', $goal))->assertRedirect();
        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put(route('savings.update', $goal), ['amount' => 1])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $otherGoal = $otherUser->savingsGoals()->create([
            'name' => 'هدف آخر',
            'target_amount' => 10000,
            'current_amount' => 0,
            'is_completed' => false,
        ]);
        $this->actingAs($user)
            ->put(route('savings.update', $otherGoal), ['amount' => 1])
            ->assertForbidden();
    }

    public function test_dashboard_uses_current_month_for_invalid_month_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-99']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('month', SalaryMonthService::for($user)->current()['key']),
            );
    }

    public function test_reports_return_the_basic_financial_contract(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->first();

        $user->incomes()->create([
            'amount' => 10000,
            'source' => 'راتب',
            'income_date' => now(),
        ]);
        $user->expenses()->create([
            'category_id' => $category->id,
            'amount' => 2500,
            'description' => 'مشتريات',
            'expense_date' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia-Version' => (string) Inertia::getVersion(),
            ])
            ->get(route('reports'));

        $response->assertOk()
            ->assertJsonPath('component', 'Reports')
            ->assertJsonPath('props.summary.total_income', 10000)
            ->assertJsonPath('props.summary.total_expenses', 2500)
            ->assertJsonPath('props.summary.net', 7500)
            ->assertJsonPath('props.topExpenses.0.amount', 2500);
        $this->assertCount(12, $response->json('props.monthly'));
        $this->assertNotEmpty($response->json('props.categories'));
    }
}
