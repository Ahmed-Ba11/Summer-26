<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingBudgetRequest;
use App\Http\Requests\OnboardingCommitmentsRequest;
use App\Http\Requests\OnboardingIncomeRequest;
use App\Models\Category;
use App\Services\RecurringTransactionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();
        $onboardingComplete = $user->onboarding_completed_at !== null;

        return Inertia::render('Onboarding', [
            'categories' => $user->categories()->orderBy('id')->get()->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
            ])->values(),
            'salaryDay' => (int) ($user->salary_day ?? 27),
            'completed' => $onboardingComplete,
            'onboardingComplete' => $onboardingComplete,
        ]);
    }

    public function income(OnboardingIncomeRequest $request, RecurringTransactionService $recurring): RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        $entries = $request->validated()['incomes'] ?? [$request->validated()];

        DB::transaction(function () use ($entries, $user, $recurring): void {
            foreach ($entries as $entry) {
                $income = $user->incomes()->create([
                    'amount' => Money::toHalalas($entry['amount']),
                    'source' => $entry['source'],
                    'description' => $entry['description'] ?? null,
                    'income_date' => $entry['income_date'] ?? now()->toDateString(),
                    'is_recurring' => $entry['is_recurring'] ?? false,
                ]);

                if ($income->is_recurring) {
                    $recurring->createFromIncome(
                        $income,
                        $entry['frequency'] ?? 'monthly',
                        $entry['next_due_date'] ?? null,
                    );
                }
            }
        });

        return redirect()->route('onboarding');
    }

    public function commitments(OnboardingCommitmentsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        DB::transaction(function () use ($validated, $user): void {
            if (array_key_exists('salary_day', $validated)) {
                $user->update(['salary_day' => $validated['salary_day']]);
            }

            foreach ($validated['commitments'] ?? [] as $commitment) {
                if ($commitment['type'] === 'installment') {
                    $monthlyAmount = Money::toHalalas($commitment['monthly_amount'] ?? $commitment['amount']);
                    $totalMonths = (int) ($commitment['total_months'] ?? 1);

                    $user->installments()->create([
                        'name' => $commitment['name'],
                        'reason' => null,
                        'icon' => $commitment['icon'] ?? null,
                        'monthly_amount' => $monthlyAmount,
                        'total_amount' => isset($commitment['total_amount'])
                            ? Money::toHalalas($commitment['total_amount'])
                            : $monthlyAmount * $totalMonths,
                        'paid_months' => 0,
                        'total_months' => $totalMonths,
                        'start_date' => $commitment['start_date'] ?? now()->format('Y-m'),
                        'is_completed' => false,
                    ]);

                    continue;
                }

                $user->bills()->create([
                    'name' => $commitment['name'],
                    'icon' => $commitment['icon'] ?? null,
                    'amount' => Money::toHalalas($commitment['amount']),
                    'due_date' => $commitment['due_date'] ?? now()->toDateString(),
                    'account_number' => $commitment['account_number'] ?? null,
                    'is_paid' => false,
                ]);
            }
        });

        return redirect()->route('onboarding');
    }

    public function budget(OnboardingBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($user->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }
        $budgets = $validated['budgets'] ?? (isset($validated['category_id']) ? [$validated] : []);
        $month = $validated['month'] ?? now()->format('Y-m');

        DB::transaction(function () use ($budgets, $month, $user): void {
            foreach ($budgets as $budget) {
                $user->budgets()->updateOrCreate(
                    ['category_id' => $budget['category_id'], 'month' => $month],
                    [
                        'amount' => Money::toHalalas($budget['amount']),
                        'alert_percentage' => $budget['alert_percentage'] ?? 80,
                    ],
                );
            }

            if ($user->onboarding_completed_at === null) {
                $user->update(['onboarding_completed_at' => now()]);
            }
        });

        return redirect()->route('dashboard');
    }
}
