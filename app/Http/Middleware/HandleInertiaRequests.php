<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\BudgetGuard;
use App\Services\CommitmentService;
use App\Services\ExpenseFundingService;
use App\Services\SalaryMonthService;
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
                'success' => fn (): mixed => $request->session()->get('success'),
                'toast' => fn (): mixed => $request->session()->get('toast'),
            ],
            'navStats' => $user ? function () use ($user): array {
                // كل الأرقام هنا على **شهر الراتب** لا الشهر التقويمي:
                // «المتبقي لك» و«الحد اليومي الآمن» أهم رقمين في التطبيق،
                // وتصفيرهما يوم 1 يجعلهما غلطاً معظم أيام شهر المستخدم.
                $salaryMonth = SalaryMonthService::for($user);
                $period = $salaryMonth->current();
                $range = $salaryMonth->rangeFor($period['key']);
                $context = BudgetGuard::for($user)->context($period['key']);
                $commitmentService = CommitmentService::for($user);
                $commitmentsTotal = $commitmentService->obligationsForPeriod($period);

                $daysLeft = $period['daysLeft'];
                $monthlyIncome = $context['monthlyIncome'];
                $budgetUsedPct = $context['budgetedTotal'] > 0
                    ? (int) round(($context['spent'] / $context['budgetedTotal']) * 100)
                    : 0;
                $monthlySavings = (int) $user->savingsDeposits()
                    ->whereBetween('deposited_at', $range)
                    ->sum('amount');
                $savingsPct = $monthlyIncome > 0
                    ? (int) round(($monthlySavings / $monthlyIncome) * 100)
                    : 0;

                $plannedSavings = max(0, $context['obligations'] - $commitmentsTotal);
                $transactionsCount = $user->expenses()
                    ->whereBetween('expense_date', $range)
                    ->count()
                    + $user->incomes()
                        ->whereBetween('income_date', $range)
                        ->count();
                $dueCommitments = $commitmentService->dueSoonCount(7, $period);

                $incomeSplit = $monthlyIncome > 0 ? [
                    ['key' => 'commitments', 'pct' => (int) round(($commitmentsTotal / $monthlyIncome) * 100), 'color' => 'var(--chart-7)'],
                    ['key' => 'savings', 'pct' => (int) round(($plannedSavings / $monthlyIncome) * 100), 'color' => 'var(--chart-3)'],
                    ['key' => 'expenses', 'pct' => (int) round(($context['spent'] / $monthlyIncome) * 100), 'color' => 'var(--chart-1)'],
                    ['key' => 'remaining', 'pct' => (int) round((max(0, $context['available']) / $monthlyIncome) * 100), 'color' => 'var(--secondary)'],
                ] : [];

                return [
                    'remaining' => $context['available'],
                    'dailySafe' => $daysLeft > 0 ? intdiv($context['available'], $daysLeft) : $context['available'],
                    'daysLeft' => $daysLeft,
                    'salaryLabel' => $period['label'],
                    'salaryRange' => $period['range'],
                    'budgetUsedPct' => $budgetUsedPct,
                    'transactionsCount' => $transactionsCount,
                    'dueCommitments' => $dueCommitments,
                    'savingsPct' => $savingsPct,
                    'incomeSplit' => $incomeSplit,
                ];
            } : null,
            'dueBillsCount' => $user ? CommitmentService::for($user)->dueSoonCount() : 0,
            'quickAdd' => $user ? function () use ($user): array {
                $salaryMonth = SalaryMonthService::for($user);
                $period = $salaryMonth->current();
                $month = $period['key'];
                $range = $salaryMonth->rangeFor($month);
                $context = BudgetGuard::for($user)->context($month);

                $categories = Category::query()
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->get()
                    ->map(function (Category $category) use ($user, $month, $range): array {
                        $spent = (int) $user->expenses()
                            ->where('category_id', $category->id)
                            ->whereBetween('expense_date', $range)
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

                // إيداع الادخار ودفع الالتزام يتمّان داخل لوح الوصول السريع
                // بلا انتقال صفحة — فقائمتاهما تُرسَل مع بقيّة سياقه.
                $commitmentService = CommitmentService::for($user);
                $commitments = $commitmentService->hydrate(
                    $user->commitments()->active()->orderBy('name')->get(),
                );
                $unpaid = array_values(array_filter(
                    $commitments,
                    fn (array $c): bool => ! $c['is_paid_this_month'],
                ));
                usort($unpaid, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));

                $today = now()->toDateString();

                return [
                    'context' => [
                        'monthlyIncome' => $context['monthlyIncome'],
                        'obligations' => $context['obligations'],
                        'spent' => $context['spent'],
                        'budgetedTotal' => $context['budgetedTotal'],
                        'daysUntilSalary' => $period['daysLeft'],
                        'salaryLabel' => $period['label'],
                    ],
                    'savingsGoals' => $user->savingsGoals()
                        ->where('is_closed', false)
                        ->orderBy('name')
                        ->get()
                        ->map(fn ($goal): array => [
                            'id' => $goal->id,
                            'name' => $goal->name,
                            'icon' => $goal->icon ?: 'vault',
                            'current' => (int) $goal->current_amount,
                            'target' => (int) $goal->target_amount,
                        ])
                        ->values()
                        ->all(),
                    'dueCommitments' => array_slice($unpaid, 0, 8),
                    'dueTodayCount' => count(array_filter(
                        $unpaid,
                        fn (array $c): bool => $c['due_date'] <= $today,
                    )),
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
