<?php

namespace Tests\Feature;

use App\Models\CommitmentPayment;
use App\Models\User;
use App\Services\CommitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommitmentsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithIncome(): User
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'راتب',
            'income_date' => now()->toDateString(),
        ]);

        return $user;
    }

    public function test_index_renders_page_with_commitments_and_period(): void
    {
        $user = $this->userWithIncome();
        $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'كهرباء',
            'amount' => 30_000,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('commitments'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Commitments')
                ->has('commitments', 1)
                ->where('commitments.0.name', 'كهرباء')
                ->where('commitments.0.amount', 30_000)
                ->where('commitments.0.is_paid_this_month', false)
                ->where('income', 100_000)
                ->where('salaryDay', 27)
                ->has('periodLabel'));
    }

    public function test_variable_bill_is_stored_without_amount(): void
    {
        $user = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'bill',
                'name' => 'كهرباء',
                'amount' => null,
                'is_variable' => true,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commitments', [
            'user_id' => $user->id,
            'kind' => 'bill',
            'name' => 'كهرباء',
            'amount' => null,
            'is_variable' => true,
        ]);
    }

    public function test_installment_monthly_is_computed_server_side(): void
    {
        $user = $this->userWithIncome();

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'installment',
                'name' => 'سيارة',
                'amount' => 999,
                'total_amount' => 36_000,
                'months_count' => 36,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('commitments', [
            'user_id' => $user->id,
            'kind' => 'installment',
            'name' => 'سيارة',
            'amount' => 1_000,
            'total_amount' => 36_000,
            'months_count' => 36,
        ]);
    }

    public function test_installment_exceeding_income_is_rejected(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'installment',
                'name' => 'سيارة',
                'total_amount' => 240_000,
                'months_count' => 12,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 1,
            ])
            ->assertInvalid('amount');

        $this->assertDatabaseCount('commitments', 0);
    }

    public function test_rent_exceeding_income_is_saved_with_warning(): void
    {
        $user = User::factory()->create(['salary_day' => 27]);
        $user->incomes()->create([
            'amount' => 10_000,
            'source' => 'راتب',
            'income_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('commitments.store'), [
                'kind' => 'rent',
                'name' => 'إيجار',
                'amount' => 20_000,
                'payment_method' => 'manual',
                'due_type' => 'month_day',
                'due_day' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('warnings.0.severity', 'warn');

        $this->assertDatabaseHas('commitments', [
            'user_id' => $user->id,
            'kind' => 'rent',
            'amount' => 20_000,
        ]);
    }

    public function test_pay_marks_committed_and_creates_payment_without_expense(): void
    {
        $user = $this->userWithIncome();
        $commitment = $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'فاتورة',
            'amount' => 30_000,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('commitments.pay', $commitment), ['amount' => 30_000])
            ->assertRedirect();

        $this->assertDatabaseHas('commitment_payments', [
            'commitment_id' => $commitment->id,
            'amount' => 30_000,
            'source' => 'manual',
        ]);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_undo_pay_removes_payment(): void
    {
        $user = $this->userWithIncome();
        $commitment = $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'فاتورة',
            'amount' => 30_000,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'is_active' => true,
        ]);
        $periodKey = CommitmentService::for($user)->currentPeriod()['key'];
        CommitmentPayment::create([
            'commitment_id' => $commitment->id,
            'amount' => 30_000,
            'paid_at' => now()->toDateString(),
            'period_key' => $periodKey,
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->delete(route('commitments.undo', $commitment))
            ->assertRedirect();

        $this->assertDatabaseCount('commitment_payments', 0);
    }

    public function test_guests_are_redirected_from_commitments(): void
    {
        $this->get(route('commitments'))->assertRedirect(route('login'));
    }

    public function test_archive_hides_commitment_from_index(): void
    {
        $user = $this->userWithIncome();
        $commitment = $user->commitments()->create([
            'kind' => 'bill',
            'name' => 'فاتورة قديمة',
            'amount' => 30_000,
            'payment_method' => 'manual',
            'due_type' => 'month_day',
            'due_day' => 5,
            'is_active' => true,
        ]);
        $commitment->payments()->create([
            'amount' => 30_000,
            'paid_at' => now()->toDateString(),
            'period_key' => CommitmentService::for($user)->currentPeriod()['key'],
            'source' => 'manual',
        ]);

        $this->actingAs($user)->delete(route('commitments.destroy', $commitment))->assertRedirect();

        $this->assertDatabaseMissing('commitments', ['id' => $commitment->id, 'is_active' => true]);
        $this->actingAs($user)->get(route('commitments'))->assertInertia(fn (Assert $page) => $page->has('commitments', 0));
    }
}
