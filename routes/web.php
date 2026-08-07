<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $totalExpenses = $user->expenses()->where('expense_date', 'like', $currentMonth.'%')->sum('amount');
        $prevExpenses = $user->expenses()->where('expense_date', 'like', $lastMonth.'%')->sum('amount');
        $totalIncome = $user->incomes()->where('income_date', 'like', $currentMonth.'%')->sum('amount');

        $categories = Category::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereNull('user_id');
        })->withCount(['expenses as total_amount' => function ($q) use ($currentMonth) {
            $q->where('expense_date', 'like', $currentMonth.'%');
        }])->get()->map(function ($cat) {
            $cat->total_amount = $cat->expenses()->where('expense_date', 'like', now()->format('Y-m').'%')->sum('amount');

            return $cat;
        });

        $monthlyExpenses = $user->expenses()
            ->selectRaw("strftime('%Y-%m', expense_date) as month, SUM(amount) as total")
            ->whereYear('expense_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recentTransactions = $user->expenses()->with('category')->latest('expense_date')->limit(5)->get()
            ->map(fn ($e) => ['type' => 'expense', 'desc' => $e->description, 'cat' => $e->category?->name, 'amount' => $e->amount, 'date' => $e->expense_date->format('Y-m-d')])
            ->concat(
                $user->incomes()->latest('income_date')->limit(3)->get()
                    ->map(fn ($i) => ['type' => 'income', 'desc' => $i->description ?? $i->source, 'cat' => $i->source, 'amount' => $i->amount, 'date' => $i->income_date->format('Y-m-d')])
            )
            ->sortByDesc('date')->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalExpenses' => (int) $totalExpenses,
                'prevExpenses' => (int) $prevExpenses,
                'totalIncome' => (int) $totalIncome,
                'balance' => (int) ($totalIncome - $totalExpenses),
                'savingsRate' => $totalIncome > 0 ? round((($totalIncome - $totalExpenses) / $totalIncome) * 100) : 0,
                'budgetTotal' => 500000, // default for MVP
            ],
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'color' => $c->color,
                'amount' => (int) $c->total_amount,
                'budget' => (int) ($user->budgets()->where('category_id', $c->id)->where('month', $currentMonth)->value('amount') ?? 0),
                'prevAmount' => (int) $c->expenses()->where('expense_date', 'like', $lastMonth.'%')->sum('amount'),
            ]),
            'monthlyExpenses' => $monthlyExpenses->map(fn ($m) => ['month' => $m->month, 'expenses' => (int) $m->total, 'income' => (int) $totalIncome]),
            'recentTransactions' => $recentTransactions,
        ]);
    })->name('dashboard');

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
                'name' => $cat->name,
                'icon' => $cat->icon ?? '📦',
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

        auth()->user()->categories()->create($validated);

        return redirect()->back();
    })->name('categories.store');

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
