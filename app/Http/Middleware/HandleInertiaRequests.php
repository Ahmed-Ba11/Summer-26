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
            'flash' => [
                'warnings' => fn (): mixed => $request->session()->get('warnings'),
            ],
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
