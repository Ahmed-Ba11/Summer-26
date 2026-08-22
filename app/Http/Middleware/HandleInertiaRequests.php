<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\BudgetGuard;
use App\Services\ExpenseFundingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'railExpanded' => $request->cookie('rail_expanded') === '1',
            'flash' => [
                'warnings' => fn (): mixed => $request->session()->get('warnings'),
            ],
            'navStats' => $user ? function () use ($user): array {
                $context = BudgetGuard::for($user)->context();
                $today = now();
                $salaryDay = (int) ($user->salary_day ?? 27);
                $salaryDate = $salaryDay === 0
                    ? $today->copy()->endOfMonth()->startOfDay()
                    : $today->copy()->setDay(min($salaryDay, $today->daysInMonth));

                if ($salaryDate->lessThanOrEqualTo($today)) {
                    $nextMonth = $today->copy()->addMonth();
                    $salaryDate = $salaryDay === 0
                        ? $nextMonth->endOfMonth()->startOfDay()
                        : $nextMonth->setDay(min($salaryDay, $nextMonth->daysInMonth));
                }

                $daysLeft = max(0, (int) $today->diffInDays($salaryDate));
                $monthlyIncome = $context['monthlyIncome'];
                $budgetUsedPct = $context['budgetedTotal'] > 0
                    ? (int) round(($context['spent'] / $context['budgetedTotal']) * 100)
                    : 0;
                $monthlySavings = (int) $user->savingsDeposits()
                    ->whereBetween('deposited_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount');
                $savingsPct = $monthlyIncome > 0
                    ? (int) round(($monthlySavings / $monthlyIncome) * 100)
                    : 0;

                $bills = (int) $user->bills()
                    ->where('is_paid', false)
                    ->whereBetween('due_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount');
                $installments = (int) $user->installments()
                    ->where('is_completed', false)
                    ->sum('monthly_amount');
                $plannedSavings = max(0, $context['obligations'] - $bills - $installments);
                $transactionsCount = $user->expenses()
                    ->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count()
                    + $user->incomes()
                        ->whereBetween('income_date', [now()->startOfMonth(), now()->endOfMonth()])
                        ->count();
                $dueCommitments = $user->bills()
                    ->where('is_paid', false)
                    ->whereBetween('due_date', [now(), now()->addDays(7)])
                    ->count();

                $incomeSplit = $monthlyIncome > 0 ? [
                    ['key' => 'bills', 'pct' => (int) round(($bills / $monthlyIncome) * 100), 'color' => 'var(--chart-7)'],
                    ['key' => 'installments', 'pct' => (int) round(($installments / $monthlyIncome) * 100), 'color' => 'var(--chart-2)'],
                    ['key' => 'savings', 'pct' => (int) round(($plannedSavings / $monthlyIncome) * 100), 'color' => 'var(--chart-3)'],
                    ['key' => 'expenses', 'pct' => (int) round(($context['spent'] / $monthlyIncome) * 100), 'color' => 'var(--chart-1)'],
                    ['key' => 'remaining', 'pct' => (int) round((max(0, $context['available']) / $monthlyIncome) * 100), 'color' => 'var(--secondary)'],
                ] : [];

                return [
                    'remaining' => $context['available'],
                    'dailySafe' => $daysLeft > 0 ? intdiv($context['available'], $daysLeft) : $context['available'],
                    'daysLeft' => $daysLeft,
                    'budgetUsedPct' => $budgetUsedPct,
                    'transactionsCount' => $transactionsCount,
                    'dueCommitments' => $dueCommitments,
                    'savingsPct' => $savingsPct,
                    'incomeSplit' => $incomeSplit,
                ];
            } : null,
            'dueBillsCount' => $request->user()
                ? $request->user()->bills()->where('is_paid', false)
                    ->whereBetween('due_date', [now(), now()->addDays(7)])->count()
                : 0,
            'quickAdd' => $user ? function () use ($user): array {
                $month = now()->format('Y-m');
                $context = BudgetGuard::for($user)->context();
                $today = now();
                $salaryDay = (int) ($user->salary_day ?? 27);
                $salaryDate = $salaryDay === 0
                    ? $today->copy()->endOfMonth()->startOfDay()
                    : $today->copy()->setDay(min($salaryDay, $today->daysInMonth));

                if ($salaryDate->lessThanOrEqualTo($today)) {
                    $nextMonth = $today->copy()->addMonth();
                    $salaryDate = $salaryDay === 0
                        ? $nextMonth->endOfMonth()->startOfDay()
                        : $nextMonth->setDay(min($salaryDay, $nextMonth->daysInMonth));
                }

                $categories = Category::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->get()
                    ->map(function (Category $category) use ($user, $month): array {
                        $spent = (int) $user->expenses()
                            ->where('category_id', $category->id)
                            ->where('expense_date', 'like', $month.'%')
                            ->sum('amount');
                        $averageEntry = (int) round((float) ($user->expenses()
                            ->where('category_id', $category->id)
                            ->where('expense_date', '>=', now()->subDays(60)->toDateString())
                            ->avg('amount') ?? 0));

                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'icon' => $category->icon ?: 'ellipsis',
                            'color' => $category->color ?: 'var(--chart-7)',
                            'budget' => (int) ($user->budgets()
                                ->where('category_id', $category->id)
                                ->where('month', $month)
                                ->value('amount') ?? 0),
                            'spent' => $spent,
                            'averageEntry' => $averageEntry,
                        ];
                    })
                    ->values()
                    ->all();

                $learned = $user->expenses()
                    ->where('expense_date', '>=', now()->subDays(60)->toDateString())
                    ->whereNotNull('description')
                    ->get(['amount', 'category_id', 'description'])
                    ->groupBy(fn ($expense): string => $expense->category_id.':'.$expense->description)
                    ->sortByDesc(fn ($entries): int => $entries->count())
                    ->take(3)
                    ->map(function ($entries): array {
                        $first = $entries->first();

                        return [
                            'label' => (string) $first->description,
                            'amount' => (int) round((float) $entries->avg('amount')),
                            'categoryId' => (int) $first->category_id,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'context' => [
                        'monthlyIncome' => $context['monthlyIncome'],
                        'obligations' => $context['obligations'],
                        'spent' => $context['spent'],
                        'budgetedTotal' => $context['budgetedTotal'],
                        'daysUntilSalary' => (int) $today->diffInDays($salaryDate),
                    ],
                    'categories' => $categories,
                    'lastCategoryId' => $user->expenses()->latest('expense_date')->value('category_id'),
                    'learned' => $learned,
                    'recurringIncome' => (int) $user->recurringTransactions()
                        ->where('type', 'income')
                        ->where('is_active', true)
                        ->sum('amount'),
                    'fundableGoals' => ExpenseFundingService::for($user)->fundableGoals(),
                ];
            } : null,
        ];
    }
}
