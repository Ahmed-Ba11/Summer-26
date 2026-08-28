<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\ReportPdfService;
use App\Services\SalaryMonthService;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(ReportRequest $request): Response
    {
        $user = $request->user();
        $salaryMonth = SalaryMonthService::for($user);
        $month = $request->validated()['month'] ?? $salaryMonth->current()['key'];

        return Inertia::render('Reports', $this->report($user, $month));
    }

    public function export(ReportRequest $request): StreamedResponse
    {
        $user = $request->user();
        $salaryMonth = SalaryMonthService::for($user);
        $month = $request->validated()['month'] ?? $salaryMonth->current()['key'];
        [$start, $end] = $salaryMonth->rangeFor($month);

        return response()->streamDownload(function () use ($user, $start, $end): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['type', 'date', 'description', 'category_or_source', 'amount_halalas']);

            $user->expenses()->with('category')
                ->whereBetween('expense_date', [$start, $end])
                ->orderBy('expense_date')
                ->each(function (Expense $expense) use ($handle): void {
                    fputcsv($handle, [
                        'expense',
                        $expense->expense_date->format('Y-m-d'),
                        $expense->description ?? '',
                        $expense->category?->name ?? '',
                        (int) $expense->amount,
                    ]);
                });

            $user->incomes()
                ->whereBetween('income_date', [$start, $end])
                ->orderBy('income_date')
                ->each(function ($income) use ($handle): void {
                    fputcsv($handle, [
                        'income',
                        $income->income_date->format('Y-m-d'),
                        $income->description ?? '',
                        $income->source,
                        (int) $income->amount,
                    ]);
                });

            fclose($handle);
        }, "report-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(ReportRequest $request): HttpResponse
    {
        $user = $request->user();
        $salaryMonth = SalaryMonthService::for($user);
        $month = $request->validated()['month'] ?? $salaryMonth->current()['key'];

        return response(ReportPdfService::for($user)->output($month), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"report-{$month}.pdf\"",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(User $user, string $month): array
    {
        $salaryMonth = SalaryMonthService::for($user);
        $period = $salaryMonth->period($month);
        [$start, $end] = $salaryMonth->rangeFor($month);
        $totalIncome = $salaryMonth->incomeFor($month);
        $totalExpenses = $salaryMonth->expensesFor($month);
        $categoryTotals = $user->expenses()
            ->selectRaw('category_id, SUM(amount) as amount')
            ->whereBetween('expense_date', [$start, $end])
            ->groupBy('category_id')
            ->pluck('amount', 'category_id');
        $budgets = $user->budgets()->where('month', $month)->pluck('amount', 'category_id');

        $categories = $user->categories()->orderBy('id')->get()->map(function (Category $category) use ($categoryTotals, $budgets): array {
            $amount = (int) ($categoryTotals[$category->id] ?? 0);
            $budget = (int) ($budgets[$category->id] ?? 0);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon ?? 'ellipsis',
                'color' => $category->color,
                'amount' => $amount,
                'budget' => $budget,
                'difference' => $budget - $amount,
                'percentage' => $budget > 0 ? (int) round(($amount / $budget) * 100) : 0,
            ];
        })->values();

        // الاتجاه على آخر اثني عشر **راتباً** لا اثني عشر شهراً تقويمياً
        $monthly = collect($salaryMonth->lastPeriods(12, $month))
            ->map(fn (array $p): array => [
                'month' => $p['key'],
                'label' => $p['label'],
                'income' => $salaryMonth->incomeFor($p['key']),
                'expenses' => $salaryMonth->expensesFor($p['key']),
            ])->values();

        $topExpenses = $user->expenses()->with('category')
            ->whereBetween('expense_date', [$start, $end])
            ->orderByDesc('amount')->latest('expense_date')->limit(5)->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'description' => $expense->description ?: ($expense->category?->name ?? 'مصروف'),
                'category' => $expense->category?->name,
                'icon' => $expense->category?->icon,
                'amount' => (int) $expense->amount,
                'date' => $expense->expense_date->format('Y-m-d'),
            ])->values();

        $netSavings = $totalIncome - $totalExpenses;

        return [
            'month' => $month,
            'salaryMonth' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'range' => $period['range'],
            ],
            'hasData' => $totalIncome > 0 || $totalExpenses > 0,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_savings' => $netSavings,
                'net' => $netSavings,
                'savings_rate' => $totalIncome > 0 ? (int) round(($netSavings / $totalIncome) * 100) : 0,
            ],
            'monthly' => $monthly,
            'categories' => $categories,
            'topExpenses' => $topExpenses,
        ];
    }
}
