<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcessRecurringTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_expense_and_income_are_created_once_and_dates_advance(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();

        $expenseTemplate = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'description' => 'اشتراك',
            'frequency' => 'monthly',
            'next_due_date' => '2026-08-18',
            'is_active' => true,
        ]);
        $incomeTemplate = $user->recurringTransactions()->create([
            'type' => 'income',
            'amount' => 100000,
            'source' => 'راتب',
            'description' => 'راتب شهري',
            'frequency' => 'weekly',
            'next_due_date' => '2026-08-18',
            'is_active' => true,
        ]);

        $this->artisan('recurring:process')->assertSuccessful();
        $this->artisan('recurring:process')->assertSuccessful();

        $expense = Expense::query()->whereBelongsTo($expenseTemplate, 'recurringTransaction')->firstOrFail();
        $income = Income::query()->whereBelongsTo($incomeTemplate, 'recurringTransaction')->firstOrFail();

        $this->assertSame(1, Expense::query()->whereBelongsTo($expenseTemplate, 'recurringTransaction')->count());
        $this->assertSame(1, Income::query()->whereBelongsTo($incomeTemplate, 'recurringTransaction')->count());
        $this->assertSame('2026-08-18', $expense->expense_date->toDateString());
        $this->assertSame('2026-08-18', $income->income_date->toDateString());
        $this->assertSame('2026-09-18', $expenseTemplate->refresh()->next_due_date->toDateString());
        $this->assertSame('2026-08-25', $incomeTemplate->refresh()->next_due_date->toDateString());
    }

    public function test_future_template_is_untouched(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $template = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'frequency' => 'daily',
            'next_due_date' => '2026-08-20',
            'is_active' => true,
        ]);

        $this->artisan('recurring:process')->assertSuccessful();

        $this->assertSame(0, Expense::query()->where('recurring_transaction_id', $template->id)->count());
        $this->assertSame('2026-08-20', $template->refresh()->next_due_date->toDateString());
    }

    public function test_inactive_template_is_untouched(): void
    {
        $user = User::factory()->create();
        $template = $user->recurringTransactions()->create([
            'type' => 'income',
            'amount' => 100000,
            'source' => 'راتب',
            'frequency' => 'daily',
            'next_due_date' => '2026-08-18',
            'is_active' => false,
        ]);

        $this->artisan('recurring:process')->assertSuccessful();

        $this->assertSame(0, Income::query()->where('recurring_transaction_id', $template->id)->count());
        $this->assertSame('2026-08-18', $template->refresh()->next_due_date->toDateString());
    }

    public function test_processing_catches_up_overdue_occurrences_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $template = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'frequency' => 'daily',
            'next_due_date' => '2026-08-17',
            'is_active' => true,
        ]);

        $this->artisan('recurring:process')->assertSuccessful();
        $this->artisan('recurring:process')->assertSuccessful();

        $this->assertSame(3, $template->expenses()->count());
        $this->assertSame('2026-08-20', $template->refresh()->next_due_date->toDateString());
        $this->assertSame(3, $template->expenses()->count());
    }

    public function test_processing_detaches_an_occurrence_linked_to_the_wrong_template_type(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $template = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'frequency' => 'monthly',
            'next_due_date' => '2026-08-19',
            'is_active' => true,
        ]);
        $income = $template->incomes()->create([
            'user_id' => $user->id,
            'amount' => 100000,
            'source' => 'راتب',
            'income_date' => '2026-08-19',
            'is_recurring' => true,
        ]);

        $this->artisan('recurring:process')->assertSuccessful();

        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);
        $this->assertSame(1, $template->expenses()->count());
    }
}
