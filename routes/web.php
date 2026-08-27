<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommitmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SetupController;
use App\Http\Requests\ExpenseIndexRequest;
use App\Http\Requests\IncomeIndexRequest;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Installment;
use App\Models\SavingsGoal;
use App\Services\BudgetGuard;
use App\Services\ExpenseFundingService;
use App\Services\RecurringTransactionService;
use App\Services\SalaryMonthService;
use App\Services\SavingsLedger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

// شاشة الترحيب — نفس الصفحة للضيف وللمستخدم الجديد، والزر وحده يختلف.
Route::inertia('/welcome', 'Welcome')->middleware('auth')->name('welcome');

// الإعداد في أربع خطوات
Route::middleware(['auth'])->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup/salary', [SetupController::class, 'salary'])->name('setup.salary');
    Route::post('/setup/commitments', [SetupController::class, 'commitments'])->name('setup.commitments');
    Route::post('/setup/budget', [SetupController::class, 'budget'])->name('setup.budget');
    Route::post('/setup/finish', [SetupController::class, 'finish'])->name('setup.finish');
    Route::post('/setup/step', [SetupController::class, 'step'])->name('setup.step');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendar', CalendarController::class)->name('calendar');

    // إقفال شهر الراتب — الفائض لا يُرحَّل صامتاً، المستخدم يقرّر وجهته.
    Route::post('/salary-month/close', function (Request $request) {
        $validated = $request->validate([
            'period_key' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'action' => 'required|in:saved,rolled,split',
            'savings_goal_id' => 'nullable|integer',
        ]);

        SalaryMonthService::for($request->user())->close(
            $validated['period_key'],
            $validated['action'],
            $validated['savings_goal_id'] ?? null,
        );

        return redirect()->back();
    })->name('salary-month.close');

    // Commitments (فواتير · إيجارات · أقساط · اشتراكات موحّدة)
    Route::get('/commitments', [CommitmentController::class, 'index'])->name('commitments');
    Route::post('/commitments', [CommitmentController::class, 'store'])->name('commitments.store');
    Route::post('/commitments/{commitment}/pay', [CommitmentController::class, 'pay'])->name('commitments.pay');
    Route::delete('/commitments/{commitment}/pay', [CommitmentController::class, 'undoPay'])->name('commitments.undo');
    Route::get('/commitments/{commitment}/edit', [CommitmentController::class, 'edit'])->name('commitments.edit');
    Route::put('/commitments/{commitment}', [CommitmentController::class, 'update'])->name('commitments.update');
    Route::delete('/commitments/{commitment}', [CommitmentController::class, 'destroy'])->name('commitments.destroy');

    // المسارات القديمة للفواتير والأقساط → إعادة توجيه إلى صفحة الالتزامات الموحّدة
    Route::redirect('/bills', '/commitments');
    Route::redirect('/installments', '/commitments');

    // Onboarding is available to authenticated users, but is not enforced by middleware.
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding/income', [OnboardingController::class, 'income'])->name('onboarding.income');
    Route::post('/onboarding/commitments', [OnboardingController::class, 'commitments'])->name('onboarding.commitments');
    Route::post('/onboarding/budget', [OnboardingController::class, 'budget'])->name('onboarding.budget');

    Route::redirect('/transactions', '/expenses');

    // Expenses
    Route::get('/expenses', function (ExpenseIndexRequest $request) {
        $user = auth()->user();
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'date';
        $direction = $filters['direction'] ?? 'desc';
        $query = $user->expenses()->with('category');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($category) => $category->where('name', $filters['category']));
        }

        if (filter_var($filters['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_recurring', true);
        }

        $expenses = $query
            ->orderBy($sort === 'amount' ? 'amount' : 'expense_date', $direction)
            ->paginate(10)
            ->withQueryString();
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->get();
        $recurringCount = $user->recurringTransactions()->where('type', 'expense')->active()->count();
        $recurringExpenses = $user->expenses()->with('category')->where('is_recurring', true)->latest('expense_date')->get();
        $mapExpense = static fn (Expense $expense): array => [
            'id' => $expense->id,
            'description' => $expense->description,
            'category' => $expense->category?->name,
            'category_icon' => $expense->category?->icon ?? 'ellipsis',
            'category_color' => $expense->category?->color ?? 'var(--chart-1)',
            'amount' => (int) $expense->amount,
            'date' => $expense->expense_date->format('Y-m-d'),
            'is_recurring' => $expense->is_recurring,
        ];

        return Inertia::render('Expenses', [
            'expenses' => $expenses->through($mapExpense)->items(),
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'recurringCount' => $recurringCount,
            'recurringExpenses' => $recurringExpenses->map($mapExpense)->values(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? null,
                'recurring' => filter_var($filters['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort' => $sort,
                'direction' => $direction,
                'page' => $expenses->currentPage(),
            ],
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
            ],
        ]);
    })->name('expenses');

    Route::post('/expenses', function (Request $request, RecurringTransactionService $recurring) {
        $request->merge([
            'expense_date' => $request->input('expense_date', $request->input('date')),
        ]);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'category_id' => [
                'required',
                Rule::exists(Category::class, 'id')->where('user_id', auth()->id()),
            ],
            'description' => 'nullable|string|max:500',
            'expense_date' => 'required|date',
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'next_due_date' => 'nullable|date',
            'funding_source' => 'nullable|in:savings,unlogged_income,overspend',
            'savings_goal_id' => 'nullable|integer',
            'income_amount' => 'nullable|integer|min:0',
            'income_source' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $amount = Money::toHalalas($validated['amount']);
        $expense = DB::transaction(function () use ($validated, $recurring, $user, $amount): Expense {
            $expense = ExpenseFundingService::for($user)->record([
                'amount' => $amount,
                'category_id' => $validated['category_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'expense_date' => $validated['expense_date'],
                'funding_source' => $validated['funding_source'] ?? null,
                'savings_goal_id' => $validated['savings_goal_id'] ?? null,
                'income_amount' => $validated['income_amount'] ?? null,
                'income_source' => $validated['income_source'] ?? null,
            ]);

            $expense->update([
                'is_recurring' => $validated['is_recurring'] ?? false,
            ]);

            if ($expense->is_recurring) {
                $recurring->createFromExpense(
                    $expense,
                    $validated['frequency'] ?? 'monthly',
                    $validated['next_due_date'] ?? null,
                );
            }

            return $expense;
        });

        $warnings = BudgetGuard::for($user)->inspectExpense(
            (int) $expense->amount,
            (int) $expense->category_id,
            SalaryMonthService::for($user)->keyFor($validated['expense_date']),
        );

        return redirect()->back()->with('warnings', $warnings);
    })->name('expenses.store');

    Route::put('/expenses/{expense}', function (Request $request, Expense $expense, RecurringTransactionService $recurring) {
        if ($expense->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'category_id' => [
                'required',
                Rule::exists(Category::class, 'id')->where('user_id', auth()->id()),
            ],
            'description' => 'nullable|string|max:500',
            'expense_date' => 'required|date',
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'next_due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated, $expense, $recurring): void {
            $expense->update([
                'category_id' => $validated['category_id'],
                'amount' => Money::toHalalas($validated['amount']),
                'description' => $validated['description'] ?? null,
                'expense_date' => $validated['expense_date'],
                'is_recurring' => $validated['is_recurring'] ?? $expense->is_recurring,
            ]);

            $recurring->syncExpense(
                $expense->refresh(),
                $validated['frequency'] ?? null,
                $validated['next_due_date'] ?? null,
            );
        });

        return redirect()->back();
    })->name('expenses.update');

    Route::delete('/expenses/{expense}', function (Expense $expense, RecurringTransactionService $recurring) {
        if ($expense->user_id !== auth()->id()) {
            abort(403);
        }
        DB::transaction(function () use ($expense, $recurring): void {
            $recurring->detachExpense($expense);
            $expense->delete();
        });

        return redirect()->back();
    })->name('expenses.destroy');

    // Income
    Route::get('/income', function (IncomeIndexRequest $request) {
        $user = auth()->user();
        $filters = $request->validated();
        $sort = $filters['sort'] ?? 'date';
        $direction = $filters['direction'] ?? 'desc';
        $query = $user->incomes();

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (filter_var($filters['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_recurring', true);
        }

        $incomes = $query
            ->orderBy($sort === 'amount' ? 'amount' : 'income_date', $direction)
            ->paginate(10)
            ->withQueryString();
        $recurringCount = $user->recurringTransactions()->where('type', 'income')->active()->count();
        $recurringIncomes = $user->incomes()->where('is_recurring', true)->latest('income_date')->get();
        $mapIncome = static fn (Income $income): array => [
            'id' => $income->id,
            'description' => $income->description ?? $income->source,
            'source' => $income->source,
            'amount' => (int) $income->amount,
            'date' => $income->income_date->format('Y-m-d'),
            'is_recurring' => $income->is_recurring,
        ];

        return Inertia::render('Income', [
            'incomes' => $incomes->through($mapIncome),
            'recurringCount' => $recurringCount,
            'recurringIncomes' => $recurringIncomes->map($mapIncome)->values(),
            'sources' => $user->incomes()->distinct()->orderBy('source')->pluck('source')->values(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'source' => $filters['source'] ?? null,
                'recurring' => filter_var($filters['recurring'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort' => $sort,
                'direction' => $direction,
                'page' => $incomes->currentPage(),
            ],
            'pagination' => [
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'total' => $incomes->total(),
            ],
        ]);
    })->name('income');

    Route::post('/income', function (Request $request, RecurringTransactionService $recurring) {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'source' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'income_date' => 'required|date',
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'next_due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated, $recurring): void {
            $income = auth()->user()->incomes()->create([
                'amount' => Money::toHalalas($validated['amount']),
                'source' => $validated['source'],
                'description' => $validated['description'] ?? null,
                'income_date' => $validated['income_date'],
                'is_recurring' => $validated['is_recurring'] ?? false,
            ]);

            if ($income->is_recurring) {
                $recurring->createFromIncome(
                    $income,
                    $validated['frequency'] ?? 'monthly',
                    $validated['next_due_date'] ?? null,
                );
            }
        });

        return redirect()->back();
    })->name('income.store');

    Route::put('/income/{income}', function (Request $request, Income $income, RecurringTransactionService $recurring) {
        if ($income->user_id !== auth()->id()) {
            abort(403);
        }
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'source' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'income_date' => 'required|date',
            'is_recurring' => 'sometimes|boolean',
            'frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'next_due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($validated, $income, $recurring): void {
            $income->update([
                'amount' => Money::toHalalas($validated['amount']),
                'source' => $validated['source'],
                'description' => $validated['description'] ?? null,
                'income_date' => $validated['income_date'],
                'is_recurring' => $validated['is_recurring'] ?? $income->is_recurring,
            ]);

            $recurring->syncIncome(
                $income->refresh(),
                $validated['frequency'] ?? null,
                $validated['next_due_date'] ?? null,
            );
        });

        return redirect()->back();
    })->name('income.update');

    Route::delete('/income/{income}', function (Income $income, RecurringTransactionService $recurring) {
        if ($income->user_id !== auth()->id()) {
            abort(403);
        }
        DB::transaction(function () use ($income, $recurring): void {
            $recurring->detachIncome($income);
            $income->delete();
        });

        return redirect()->back();
    })->name('income.destroy');

    // Budgets
    Route::get('/budgets', function () {
        $user = auth()->user();
        $salaryMonth = SalaryMonthService::for($user);
        $period = $salaryMonth->current();
        $currentMonth = $period['key'];
        $periodRange = $salaryMonth->rangeFor($currentMonth);
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->get();

        $budgets = $categories->map(function ($cat) use ($user, $currentMonth, $periodRange) {
            $budget = $user->budgets()->where('category_id', $cat->id)->where('month', $currentMonth)->first();
            $spent = (int) $user->expenses()->where('category_id', $cat->id)
                ->whereBetween('expense_date', $periodRange)
                ->sum('amount');

            return [
                'id' => $budget?->id,
                'category_id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon ?? 'ellipsis',
                'color' => $cat->color ?? '#6b7280',
                'budget' => (int) ($budget?->amount ?? 0),
                'spent' => $spent,
                'rollover' => 0,
                'alert_percentage' => (int) ($budget?->alert_percentage ?? 80),
            ];
        });

        $totalBudget = $budgets->sum('budget');
        $totalSpent = $budgets->sum('spent');

        return Inertia::render('Budgets', [
            'budgets' => $budgets,
            'stats' => [
                'totalBudget' => $totalBudget,
                'totalSpent' => $totalSpent,
                'remaining' => $totalBudget - $totalSpent,
                'rollover' => 0,
            ],
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
                'daysLeft' => $period['daysLeft'],
            ],
        ]);
    })->name('budgets');

    Route::post('/budgets', function (Request $request) {
        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists(Category::class, 'id')->where('user_id', auth()->id()),
            ],
            'amount' => 'required|numeric|min:0|decimal:0,2',
            'month' => 'nullable|string|size:7',
            'alert_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $user = auth()->user();
        $month = $validated['month'] ?? SalaryMonthService::for($user)->current()['key'];
        $amount = Money::toHalalas($validated['amount']);
        $previousAmount = (int) $user->budgets()
            ->where('category_id', $validated['category_id'])
            ->where('month', $month)
            ->value('amount');

        BudgetGuard::for($user)->assertBudgetFits($amount, $previousAmount, $month);

        $user->budgets()->updateOrCreate(
            ['category_id' => $validated['category_id'], 'month' => $month],
            ['amount' => $amount, 'alert_percentage' => $validated['alert_percentage'] ?? 80]
        );

        return redirect()->back();
    })->name('budgets.store');

    Route::put('/budgets/{budget}', function (Request $request, Budget $budget) {
        if ($budget->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0|decimal:0,2',
            'alert_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $budget->update([
            'amount' => Money::toHalalas($validated['amount']),
            'alert_percentage' => $validated['alert_percentage'] ?? $budget->alert_percentage,
        ]);

        return redirect()->back();
    })->name('budgets.update');

    Route::delete('/budgets/{budget}', function (Budget $budget) {
        if ($budget->user_id !== auth()->id()) {
            abort(403);
        }
        $budget->delete();

        return redirect()->back();
    })->name('budgets.destroy');

    // Categories
    Route::post('/categories', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        auth()->user()->categories()->firstOrCreate(
            ['name' => $validated['name']],
            ['icon' => $validated['icon'] ?? null, 'color' => $validated['color'] ?? null]
        );

        return redirect()->back();
    })->name('categories.store');

    // Savings
    Route::get('/savings', function () {
        $user = auth()->user();
        $salaryMonth = SalaryMonthService::for($user);
        $period = $salaryMonth->current();

        $goals = $user->savingsGoals()->latest()->get()->map(fn (SavingsGoal $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'icon' => $g->icon,
            'target_amount' => (int) $g->target_amount,
            'current_amount' => (int) $g->current_amount,
            'target_date' => $g->target_date?->format('Y-m-d'),
            'is_completed' => (bool) $g->is_completed,
            'is_closed' => (bool) $g->is_closed,
        ]);

        $totalSaved = (int) $user->savingsGoals()->sum('current_amount');
        $monthlyIncome = $salaryMonth->incomeFor($period['key']);
        $monthlyDeposits = SavingsLedger::for($user)->netForPeriod($period['key']);

        return Inertia::render('Savings', [
            'goals' => $goals,
            'stats' => [
                'total_saved' => $totalSaved,
                'monthly_income' => $monthlyIncome,
                'monthly_deposits' => $monthlyDeposits,
                'savings_rate' => $monthlyIncome > 0 ? (int) round(($monthlyDeposits / $monthlyIncome) * 100) : 0,
            ],
            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
                'daysLeft' => $period['daysLeft'],
            ],
        ]);
    })->name('savings');

    Route::post('/savings', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'target_amount' => 'required|numeric|min:0|decimal:0,2',
            'target_date' => 'nullable|date',
        ]);

        $targetAmount = Money::toHalalas($validated['target_amount']);
        if (! empty($validated['target_date'])) {
            BudgetGuard::for(auth()->user())->assertSavingsGoalFits(
                $targetAmount,
                0,
                $validated['target_date'],
            );
        }

        auth()->user()->savingsGoals()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'target_amount' => $targetAmount,
            'current_amount' => 0,
            'target_date' => $validated['target_date'] ?? null,
            'is_completed' => false,
            'is_closed' => false,
        ]);

        return redirect()->back();
    })->name('savings.store');

    Route::put('/savings/{goal}', function (Request $request, SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
        ]);

        $addition = Money::toHalalas($validated['amount']);
        $newAmount = (int) $goal->current_amount + $addition;

        if ($goal->is_closed) {
            throw ValidationException::withMessages([
                'amount' => 'هذا الهدف مكتمل ومغلق بالفعل.',
            ]);
        }

        SavingsLedger::for(auth()->user())->deposit($goal, $addition);

        $response = redirect()->back();
        $overage = $newAmount - (int) $goal->target_amount;

        if ($overage > 0) {
            return $response->with('warnings', [[
                'severity' => 'success',
                'title' => 'تجاوزت هدفك الادخاري',
                'overage' => $overage,
                'detail' => null,
            ]]);
        }

        return $response;
    })->name('savings.update');

    Route::delete('/savings/{goal}', function (SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $goal->delete();

        return redirect()->back();
    })->name('savings.destroy');

    /**
     * إقفال الهدف — «خلاص ما عدت أحتاجه»، لا «بلغتُ المبلغ».
     *
     * كان هذا المسار يكتب `is_completed = true` بلا أي مقارنة، فهدف بـ
     * 2,000 من 30,000 يظهر «مكتمل» في بطاقته وفي عدّاد «X من Y مكتمل».
     * الإقفال قرار المستخدم، أمّا الاكتمال فواقعة يقرّرها الرصيد وحده.
     */
    Route::put('/savings/{goal}/complete', function (SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $reached = $goal->hasReachedTarget();

        $goal->update([
            'is_completed' => $reached,
            'is_closed' => true,
        ]);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => $reached
                ? "بلّغت هدف «{$goal->name}» — مبروك"
                : "أُقفل هدف «{$goal->name}» قبل بلوغه",
        ]);
    })->name('savings.complete');

    // Installments
    Route::get('/installments', function () {
        $user = auth()->user();

        $installments = $user->installments()->latest()->get()->map(fn (Installment $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'reason' => $i->reason,
            'icon' => $i->icon,
            'monthly_amount' => (int) $i->monthly_amount,
            'total_amount' => (int) $i->total_amount,
            'paid_months' => (int) $i->paid_months,
            'total_months' => (int) $i->total_months,
            'start_date' => $i->start_date,
            'is_completed' => (bool) $i->is_completed,
        ]);

        $activeCount = $user->installments()->where('is_completed', false)->count();
        $totalMonthly = (int) $user->installments()->where('is_completed', false)->sum('monthly_amount');
        $completedCount = $user->installments()->where('is_completed', true)->count();

        return Inertia::render('Installments', [
            'installments' => $installments,
            'stats' => [
                'active_count' => $activeCount,
                'total_monthly' => $totalMonthly,
                'completed_count' => $completedCount,
            ],
        ]);
    })->name('installments');

    Route::post('/installments', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'monthly_amount' => 'required|numeric|min:0|decimal:0,2',
            'total_amount' => 'required|numeric|min:0|decimal:0,2',
            'total_months' => 'required|integer|min:1',
            'start_date' => 'required|date_format:Y-m',
        ]);

        $monthlyAmount = Money::toHalalas($validated['monthly_amount']);
        BudgetGuard::for(auth()->user())->assertCommitmentFits($monthlyAmount, 0, 'monthly_amount');

        auth()->user()->installments()->create([
            'name' => $validated['name'],
            'reason' => $validated['reason'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'monthly_amount' => $monthlyAmount,
            'total_amount' => Money::toHalalas($validated['total_amount']),
            'paid_months' => 0,
            'total_months' => $validated['total_months'],
            'start_date' => $validated['start_date'],
            'is_completed' => false,
        ]);

        return redirect()->back();
    })->name('installments.store');

    Route::put('/installments/{installment}/pay', function (Installment $installment) {
        if ($installment->user_id !== auth()->id()) {
            abort(403);
        }

        if ($installment->is_completed || $installment->paid_months >= $installment->total_months) {
            throw ValidationException::withMessages([
                'paid_months' => 'هذا القسط مكتمل بالفعل.',
            ]);
        }

        $newPaid = $installment->paid_months + 1;
        $installment->update([
            'paid_months' => $newPaid,
            'is_completed' => $newPaid >= $installment->total_months,
        ]);

        return redirect()->back();
    })->name('installments.pay');

    Route::delete('/installments/{installment}', function (Installment $installment) {
        if ($installment->user_id !== auth()->id()) {
            abort(403);
        }

        $installment->delete();

        return redirect()->back();
    })->name('installments.destroy');

    // Bills
    Route::get('/bills', function () {
        $user = auth()->user();

        $bills = $user->bills()->latest('due_date')->get()->map(fn (Bill $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'icon' => $b->icon,
            'amount' => $b->amount !== null ? (int) $b->amount : null,
            'due_date' => $b->due_date->format('Y-m-d'),
            'account_number' => $b->account_number,
            'is_paid' => (bool) $b->is_paid,
        ]);

        $upcomingCount = $user->bills()->where('is_paid', false)->count();
        $totalDue = (int) $user->bills()->where('is_paid', false)->sum('amount');
        $paidCount = $user->bills()->where('is_paid', true)->count();

        return Inertia::render('Bills', [
            'bills' => $bills,
            'stats' => [
                'upcoming_count' => $upcomingCount,
                'total_due' => $totalDue,
                'paid_count' => $paidCount,
            ],
        ]);
    })->name('bills');

    Route::post('/bills', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'amount' => 'nullable|numeric|min:0|decimal:0,2',
            'due_date' => 'required|date',
            'account_number' => 'nullable|string|max:255',
        ]);

        $amount = isset($validated['amount']) ? Money::toHalalas($validated['amount']) : null;
        if ($amount !== null) {
            BudgetGuard::for(auth()->user())->assertCommitmentFits($amount, 0, 'amount');
        }

        auth()->user()->bills()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'amount' => $amount,
            'due_date' => $validated['due_date'],
            'account_number' => $validated['account_number'] ?? null,
            'is_paid' => false,
        ]);

        return redirect()->back();
    })->name('bills.store');

    Route::put('/bills/{bill}', function (Request $request, Bill $bill) {
        if ($bill->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'amount' => 'nullable|numeric|min:0|decimal:0,2',
            'due_date' => 'required|date',
            'account_number' => 'nullable|string|max:255',
            'is_paid' => 'sometimes|boolean',
        ]);

        $bill->update([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'amount' => isset($validated['amount']) ? Money::toHalalas($validated['amount']) : null,
            'due_date' => $validated['due_date'],
            'account_number' => $validated['account_number'] ?? null,
            'is_paid' => $validated['is_paid'] ?? $bill->is_paid,
        ]);

        return redirect()->back();
    })->name('bills.update');

    Route::put('/bills/{bill}/pay', function (Bill $bill) {
        if ($bill->user_id !== auth()->id()) {
            abort(403);
        }

        $bill->update(['is_paid' => true]);

        return redirect()->back();
    })->name('bills.pay');

    Route::put('/bills/{bill}/unpay', function (Bill $bill) {
        if ($bill->user_id !== auth()->id()) {
            abort(403);
        }

        $bill->update(['is_paid' => false]);

        return redirect()->back();
    })->name('bills.unpay');

    Route::delete('/bills/{bill}', function (Bill $bill) {
        if ($bill->user_id !== auth()->id()) {
            abort(403);
        }

        $bill->delete();

        return redirect()->back();
    })->name('bills.destroy');

    // Recurring transactions are templates only; no automatic processing is claimed yet.
    Route::get('/recurring', [RecurringTransactionController::class, 'index'])->name('recurring');
    Route::post('/recurring', [RecurringTransactionController::class, 'store'])->name('recurring.store');
    Route::put('/recurring/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring.update');
    Route::delete('/recurring/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring.destroy');

    // Reports
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');

    // Assistant
    Route::get('/assistant', AssistantController::class)->name('assistant');
    Route::post('/assistant/chat', [AssistantController::class, 'chat'])
        ->middleware('throttle:30,1')
        ->name('assistant.chat');
});
require __DIR__.'/settings.php';
