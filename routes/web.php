<?php

use App\Http\Controllers\DashboardController;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Installment;
use App\Models\SavingsGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Expenses
    Route::get('/expenses', function () {
        $user = auth()->user();
        $expenses = $user->expenses()->with('category')->latest('expense_date')->paginate(10);
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->get();
        $recurringCount = $user->recurringTransactions()->where('type', 'expense')->active()->count();

        return Inertia::render('Expenses', [
            'expenses' => $expenses->through(fn ($e) => [
                'id' => $e->id,
                'description' => $e->description,
                'category' => $e->category?->name,
                'amount' => (int) $e->amount,
                'date' => $e->expense_date->format('Y-m-d'),
                'is_recurring' => $e->is_recurring,
            ])->items(),
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'recurringCount' => $recurringCount,
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
            ],
        ]);
    })->name('expenses');

    Route::post('/expenses', function (Request $request) {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'expense_date' => 'required|date',
        ]);

        auth()->user()->expenses()->create([
            'category_id' => $validated['category_id'],
            'amount' => (int) ($validated['amount'] * 100),
            'description' => $validated['description'],
            'expense_date' => $validated['expense_date'],
        ]);

        return redirect()->back();
    })->name('expenses.store');

    Route::put('/expenses/{expense}', function (Request $request, Expense $expense) {
        if ($expense->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'expense_date' => 'required|date',
        ]);

        $expense->update([
            'category_id' => $validated['category_id'],
            'amount' => (int) ($validated['amount'] * 100),
            'description' => $validated['description'],
            'expense_date' => $validated['expense_date'],
        ]);

        return redirect()->back();
    })->name('expenses.update');

    Route::delete('/expenses/{expense}', function (Expense $expense) {
        if ($expense->user_id !== auth()->id()) {
            abort(403);
        }
        $expense->delete();

        return redirect()->back();
    })->name('expenses.destroy');

    // Income
    Route::get('/income', function () {
        $user = auth()->user();
        $incomes = $user->incomes()->latest('income_date')->paginate(10);
        $recurringCount = $user->recurringTransactions()->where('type', 'income')->active()->count();

        return Inertia::render('Income', [
            'incomes' => $incomes->through(fn ($i) => [
                'id' => $i->id,
                'description' => $i->description ?? $i->source,
                'source' => $i->source,
                'amount' => (int) $i->amount,
                'date' => $i->income_date->format('Y-m-d'),
                'is_recurring' => $i->is_recurring,
            ]),
            'recurringCount' => $recurringCount,
            'pagination' => [
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'total' => $incomes->total(),
            ],
        ]);
    })->name('income');

    Route::post('/income', function (Request $request) {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'source' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'income_date' => 'required|date',
        ]);

        auth()->user()->incomes()->create([
            'amount' => (int) ($validated['amount'] * 100),
            'source' => $validated['source'],
            'description' => $validated['description'],
            'income_date' => $validated['income_date'],
        ]);

        return redirect()->back();
    })->name('income.store');

    Route::put('/income/{income}', function (Request $request, Income $income) {
        if ($income->user_id !== auth()->id()) {
            abort(403);
        }
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'source' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'income_date' => 'required|date',
        ]);

        $income->update([
            'amount' => (int) ($validated['amount'] * 100),
            'source' => $validated['source'],
            'description' => $validated['description'],
            'income_date' => $validated['income_date'],
        ]);

        return redirect()->back();
    })->name('income.update');

    Route::delete('/income/{income}', function (Income $income) {
        if ($income->user_id !== auth()->id()) {
            abort(403);
        }
        $income->delete();

        return redirect()->back();
    })->name('income.destroy');

    // Budgets
    Route::get('/budgets', function () {
        $user = auth()->user();
        $currentMonth = now()->format('Y-m');
        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->get();

        $budgets = $categories->map(function ($cat) use ($user, $currentMonth) {
            $budget = $user->budgets()->where('category_id', $cat->id)->where('month', $currentMonth)->first();
            $spent = (int) $user->expenses()->where('category_id', $cat->id)->where('expense_date', 'like', $currentMonth.'%')->sum('amount');

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
        ]);
    })->name('budgets');

    Route::post('/budgets', function (Request $request) {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0',
            'month' => 'nullable|string|size:7',
            'alert_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        auth()->user()->budgets()->updateOrCreate(
            ['category_id' => $validated['category_id'], 'month' => $validated['month'] ?? now()->format('Y-m')],
            ['amount' => (int) ($validated['amount'] * 100), 'alert_percentage' => $validated['alert_percentage'] ?? 80]
        );

        return redirect()->back();
    })->name('budgets.store');

    Route::put('/budgets/{budget}', function (Request $request, Budget $budget) {
        if ($budget->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'alert_percentage' => 'nullable|integer|min:1|max:100',
        ]);

        $budget->update([
            'amount' => (int) ($validated['amount'] * 100),
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
        $currentMonth = now()->format('Y-m');

        $goals = $user->savingsGoals()->latest()->get()->map(fn (SavingsGoal $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'icon' => $g->icon,
            'target_amount' => (int) $g->target_amount,
            'current_amount' => (int) $g->current_amount,
            'target_date' => $g->target_date?->format('Y-m-d'),
            'is_completed' => (bool) $g->is_completed,
        ]);

        $totalSaved = (int) $user->savingsGoals()->sum('current_amount');
        $monthlyIncome = (int) $user->incomes()->where('income_date', 'like', $currentMonth.'%')->sum('amount');
        $savingsRate = $monthlyIncome > 0 ? (int) round(($totalSaved / $monthlyIncome) * 100) : 0;

        return Inertia::render('Savings', [
            'goals' => $goals,
            'stats' => [
                'total_saved' => $totalSaved,
                'monthly_income' => $monthlyIncome,
                'savings_rate' => $savingsRate,
            ],
        ]);
    })->name('savings');

    Route::post('/savings', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'target_amount' => 'required|numeric|min:0',
            'target_date' => 'nullable|date',
        ]);

        auth()->user()->savingsGoals()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'target_amount' => (int) ($validated['target_amount'] * 100),
            'current_amount' => 0,
            'target_date' => $validated['target_date'] ?? null,
            'is_completed' => false,
        ]);

        return redirect()->back();
    })->name('savings.store');

    Route::put('/savings/{goal}', function (Request $request, SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $goal->update([
            'current_amount' => (int) $goal->current_amount + (int) ($validated['amount'] * 100),
        ]);

        return redirect()->back();
    })->name('savings.update');

    Route::delete('/savings/{goal}', function (SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $goal->delete();

        return redirect()->back();
    })->name('savings.destroy');

    Route::put('/savings/{goal}/complete', function (SavingsGoal $goal) {
        if ($goal->user_id !== auth()->id()) {
            abort(403);
        }

        $goal->update(['is_completed' => true]);

        return redirect()->back();
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
            'monthly_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'total_months' => 'required|integer|min:1',
            'start_date' => 'required|string|size:7',
        ]);

        auth()->user()->installments()->create([
            'name' => $validated['name'],
            'reason' => $validated['reason'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'monthly_amount' => (int) ($validated['monthly_amount'] * 100),
            'total_amount' => (int) ($validated['total_amount'] * 100),
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
            'amount' => 'nullable|numeric|min:0',
            'due_date' => 'required|date',
            'account_number' => 'nullable|string|max:255',
        ]);

        auth()->user()->bills()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'amount' => isset($validated['amount']) ? (int) ($validated['amount'] * 100) : null,
            'due_date' => $validated['due_date'],
            'account_number' => $validated['account_number'] ?? null,
            'is_paid' => false,
        ]);

        return redirect()->back();
    })->name('bills.store');

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

    // Recurring
    Route::get('/recurring', function () {
        $user = auth()->user();

        return Inertia::render('Recurring', [
            'transactions' => $user->recurringTransactions()->with('category')->latest()->get()->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'description' => $r->description,
                'category' => $r->category?->name,
                'source' => $r->source,
                'amount' => (int) $r->amount,
                'frequency' => $r->frequency,
                'next_due_date' => $r->next_due_date->format('Y-m-d'),
                'is_active' => $r->is_active,
            ]),
        ]);
    })->name('recurring');
});
require __DIR__.'/settings.php';
