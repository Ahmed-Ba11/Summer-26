<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Tests\TestCase;

class BackendDataCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_requires_income_sources_and_month_only_installment_start_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.income'), [
                'incomes' => [['amount' => '100', 'source' => '']],
            ])
            ->assertInvalid('incomes.0.source');

        $this->actingAs($user)
            ->post(route('onboarding.commitments'), [
                'commitments' => [[
                    'type' => 'installment',
                    'name' => 'قسط',
                    'amount' => '100',
                    'start_date' => '2026-08-01',
                ]],
            ])
            ->assertInvalid('commitments.0.start_date');

        $this->actingAs($user)
            ->post(route('onboarding.commitments'), [
                'commitments' => [[
                    'type' => 'installment',
                    'name' => 'قسط',
                    'amount' => '100',
                    'start_date' => '2026-08',
                ]],
            ])
            ->assertRedirectToRoute('onboarding');

        $this->assertDatabaseHas('installments', [
            'user_id' => $user->id,
            'start_date' => '2026-08',
        ]);
    }

    public function test_onboarding_completion_uses_timestamp_and_repeated_completion_is_safe(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);
        $category = $user->categories()->firstOrFail();

        $this->actingAs($user)
            ->get(route('onboarding'))
            ->assertInertia(fn ($page) => $page
                ->where('completed', true)
                ->where('onboardingComplete', true));

        $this->actingAs($user)
            ->post(route('onboarding.budget'), [
                'budgets' => [['category_id' => $category->id, 'amount' => '100']],
            ])
            ->assertRedirectToRoute('dashboard');

        $this->assertDatabaseCount('budgets', 0);

        $legacyUser = User::factory()->create();
        $legacyCategory = $legacyUser->categories()->firstOrFail();
        $legacyUser->incomes()->create([
            'amount' => 10000,
            'source' => 'مصدر حقيقي',
            'income_date' => now(),
        ]);
        $legacyUser->budgets()->create([
            'category_id' => $legacyCategory->id,
            'amount' => 5000,
            'month' => now()->format('Y-m'),
        ]);

        $this->actingAs($legacyUser)
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'))
            ->assertJsonPath('props.onboardingComplete', false);
    }

    public function test_expense_and_income_filters_are_applied_and_return_filter_state(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $otherCategory = $user->categories()->get()->last();

        $user->expenses()->createMany([
            [
                'category_id' => $category->id,
                'amount' => 1000,
                'description' => 'مشتريات',
                'expense_date' => '2026-08-01',
                'is_recurring' => false,
            ],
            [
                'category_id' => $otherCategory->id,
                'amount' => 2500,
                'description' => 'اشتراك',
                'expense_date' => '2026-08-02',
                'is_recurring' => true,
            ],
        ]);
        $otherUser->expenses()->create([
            'category_id' => $otherUser->categories()->firstOrFail()->id,
            'amount' => 9999,
            'description' => 'اشتراك',
            'expense_date' => '2026-08-03',
        ]);

        $expenseResponse = $this->actingAs($user)
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('expenses', [
                'search' => 'اشتراك',
                'category' => $otherCategory->name,
                'recurring' => '1',
                'sort' => 'amount',
                'direction' => 'asc',
                'page' => 1,
            ]));

        $expenseResponse->assertOk()
            ->assertJsonPath('props.expenses.0.description', 'اشتراك')
            ->assertJsonPath('props.expenses.1', null)
            ->assertJsonPath('props.filters.category', $otherCategory->name)
            ->assertJsonPath('props.filters.recurring', true)
            ->assertJsonPath('props.filters.sort', 'amount')
            ->assertJsonPath('props.filters.direction', 'asc');

        $user->incomes()->createMany([
            [
                'amount' => 10000,
                'source' => 'راتب',
                'description' => 'راتب شهري',
                'income_date' => '2026-08-01',
                'is_recurring' => true,
            ],
            [
                'amount' => 20000,
                'source' => 'عمل حر',
                'description' => 'مشروع',
                'income_date' => '2026-08-02',
                'is_recurring' => false,
            ],
        ]);

        $incomeResponse = $this->actingAs($user)
            ->withoutMiddleware(HandleInertiaRequests::class)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('income', [
                'search' => 'راتب',
                'source' => 'راتب',
                'recurring' => '1',
                'sort' => 'date',
                'direction' => 'desc',
            ]));

        $incomeResponse->assertOk()
            ->assertJsonPath('props.incomes.data.0.source', 'راتب')
            ->assertJsonPath('props.incomes.data.1', null)
            ->assertJsonPath('props.filters.source', 'راتب')
            ->assertJsonPath('props.recurringIncomes.0.source', 'راتب');

        $this->actingAs($user)
            ->get(route('expenses', ['sort' => 'unsafe']))
            ->assertInvalid('sort');
    }

    public function test_linked_recurring_templates_follow_transaction_updates_and_cancellation(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $user->incomes()->create([
            'amount' => 100_000,
            'source' => 'دخل اختباري',
            'income_date' => now(),
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'amount' => '10.00',
            'category_id' => $category->id,
            'description' => 'اشتراك قديم',
            'expense_date' => '2026-08-01',
            'is_recurring' => true,
        ])->assertRedirect();

        $expense = $user->expenses()->firstOrFail();
        $template = $user->recurringTransactions()->firstOrFail();
        $this->assertSame(1000, $expense->amount);
        $this->assertSame($template->id, $expense->recurring_transaction_id);

        $this->actingAs($user)->put(route('recurring.update', $template), [
            'type' => 'income',
            'source' => 'راتب',
            'amount' => '10.00',
            'description' => 'نوع خاطئ',
            'frequency' => 'monthly',
            'next_due_date' => '2026-09-01',
        ])->assertInvalid('type');

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $template->id,
            'type' => 'expense',
        ]);

        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'amount' => '12.34',
            'category_id' => $category->id,
            'description' => 'اشتراك محدث',
            'expense_date' => '2026-08-02',
            'is_recurring' => true,
            'frequency' => 'yearly',
            'next_due_date' => '2027-01-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('recurring_transactions', [
            'id' => $template->id,
            'amount' => 1234,
            'description' => 'اشتراك محدث',
            'frequency' => 'yearly',
        ]);
        $this->assertSame('2027-01-01', $template->refresh()->next_due_date->format('Y-m-d'));

        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'amount' => '12.35',
            'category_id' => $category->id,
            'description' => 'اشتراك عادي',
            'expense_date' => '2026-08-02',
            'is_recurring' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);
        $this->assertSoftDeleted('recurring_transactions', ['id' => $template->id]);

        $this->actingAs($user)->post(route('income.store'), [
            'amount' => '100.00',
            'source' => 'راتب',
            'income_date' => '2026-08-01',
            'is_recurring' => true,
        ])->assertRedirect();

        $income = $user->incomes()->where('source', 'راتب')->firstOrFail();
        $incomeTemplate = $user->recurringTransactions()->where('type', 'income')->firstOrFail();

        $this->actingAs($user)->delete(route('recurring.destroy', $incomeTemplate))->assertRedirect();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $incomeTemplate->id]);
        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);

        $this->actingAs($user)->post(route('income.store'), [
            'amount' => '200',
            'source' => 'عمل حر',
            'income_date' => '2026-08-03',
            'is_recurring' => true,
        ])->assertRedirect();

        $incomeToDelete = $user->incomes()->where('source', 'عمل حر')->firstOrFail();
        $templateToDelete = $user->recurringTransactions()->where('type', 'income')->firstOrFail();

        $this->actingAs($user)->delete(route('income.destroy', $incomeToDelete))->assertRedirect();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $templateToDelete->id]);
        $this->assertSoftDeleted('incomes', ['id' => $incomeToDelete->id]);
    }

    public function test_transaction_amount_validation_rejects_precision_and_scientific_notation(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => '10.005',
                'category_id' => $category->id,
                'expense_date' => '2026-08-01',
            ])
            ->assertInvalid('amount');

        $this->actingAs($user)
            ->post(route('income.store'), [
                'amount' => '1e3',
                'source' => 'راتب',
                'income_date' => '2026-08-01',
            ])
            ->assertInvalid('amount');
    }

    public function test_deleting_one_occurrence_keeps_template_until_the_last_occurrence_is_deleted(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $template = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'description' => 'اشتراك',
            'frequency' => 'monthly',
            'next_due_date' => '2026-08-01',
            'is_active' => true,
        ]);
        $first = $template->expenses()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 2500,
            'description' => 'الأول',
            'expense_date' => '2026-08-01',
            'is_recurring' => true,
        ]);
        $second = $template->expenses()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 2500,
            'description' => 'الثاني',
            'expense_date' => '2026-09-01',
            'is_recurring' => true,
        ]);

        $this->actingAs($user)->delete(route('expenses.destroy', $first))->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'id' => $first->id,
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => $second->id,
            'is_recurring' => true,
            'recurring_transaction_id' => $template->id,
        ]);
        $this->assertDatabaseHas('recurring_transactions', ['id' => $template->id, 'deleted_at' => null]);

        $this->actingAs($user)->delete(route('expenses.destroy', $second))->assertRedirect();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $template->id]);
    }

    public function test_deleting_template_detaches_all_expense_occurrences(): void
    {
        $user = User::factory()->create();
        $category = $user->categories()->firstOrFail();
        $template = $user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => 2500,
            'frequency' => 'monthly',
            'next_due_date' => '2026-08-01',
            'is_active' => true,
        ]);
        $template->expenses()->createMany([
            [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'amount' => 2500,
                'expense_date' => '2026-08-01',
                'is_recurring' => true,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'amount' => 2500,
                'expense_date' => '2026-09-01',
                'is_recurring' => true,
            ],
        ]);

        $this->actingAs($user)->delete(route('recurring.destroy', $template))->assertRedirect();

        $this->assertSoftDeleted('recurring_transactions', ['id' => $template->id]);
        $this->assertSame(0, $user->expenses()->where('recurring_transaction_id', $template->id)->count());
        $this->assertSame(0, $user->expenses()->where('is_recurring', true)->count());
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) Inertia::getVersion(),
        ];
    }
}
