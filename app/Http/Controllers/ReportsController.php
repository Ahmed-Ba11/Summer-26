<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\ReportPdfService;
use App\Services\SalaryMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * المدد المتاحة — أياماً متدحرجة تنتهي اليوم.
     *
     * «شهرياً» وحده لا يكفي: من يسأل «كم صرفت هذا الأسبوعين؟» لا يجد جواباً
     * في تقرير يبدأ يوم الراتب، ومن يقارن شهرين متتاليين لا يجده في مدى
     * متدحرج. فالاثنان معاً لا أحدهما.
     */
    private const DAY_RANGES = ['15d' => 15, '30d' => 30, '60d' => 60];

    public function index(ReportRequest $request): Response
    {
        $user = $request->user();
        $window = $this->window($user, $request->validated());

        return Inertia::render('Reports', $this->report($user, $window));
    }

    /**
     * التصدير — PDF وحده.
     *
     * كان معه CSV، وحُذف: صيغتان لنفس التقرير تفرضان على المستخدم اختياراً
     * لا يملك معياره، وملف CSV بالعربية يفتحه Excel مشوّهاً في الغالب.
     * التقرير مستند يُقرأ لا جدول يُعالَج.
     */
    public function exportPdf(ReportRequest $request): HttpResponse
    {
        $user = $request->user();
        $window = $this->window($user, $request->validated());
        $service = ReportPdfService::for($user);

        [$bytes, $name] = $window['key'] !== null
            ? [$service->output($window['key']), "report-{$window['key']}.pdf"]
            : [$service->outputDays($window['days']), "report-last-{$window['days']}-days.pdf"];

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$name}\"",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * النافذة المطلوبة: فترة راتب كاملة أو مدى متدحرج بالأيام.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function window(User $user, array $validated): array
    {
        $salaryMonth = SalaryMonthService::for($user);
        $range = is_string($validated['range'] ?? null) ? $validated['range'] : 'month';

        if (isset(self::DAY_RANGES[$range])) {
            $days = self::DAY_RANGES[$range];
            $end = CarbonImmutable::today()->endOfDay();
            $start = CarbonImmutable::today()->subDays($days - 1)->startOfDay();

            return [
                'range' => $range,
                'key' => null,
                'days' => $days,
                'month' => null,
                'label' => 'آخر '.$days.' يوم',
                'rangeLabel' => ReportPdfService::dayLabel($start).' ← '.ReportPdfService::dayLabel($end),
                'start' => $start,
                'end' => $end,
                // الاتجاه يُرسى على الفترة التي ينتهي فيها المدى
                'anchor' => $salaryMonth->keyFor($end),
            ];
        }

        $month = $validated['month'] ?? $salaryMonth->current()['key'];
        $period = $salaryMonth->period($month);
        [$start, $end] = $salaryMonth->rangeFor($month);

        return [
            'range' => 'month',
            'key' => $month,
            'days' => null,
            'month' => $month,
            'label' => $period['label'],
            'rangeLabel' => $period['range'],
            'start' => $start,
            'end' => $end,
            'anchor' => $month,
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    private function report(User $user, array $window): array
    {
        $salaryMonth = SalaryMonthService::for($user);
        $start = $window['start'];
        $end = $window['end'];

        $totalIncome = (int) $user->incomes()->whereBetween('income_date', [$start, $end])->sum('amount');
        $totalExpenses = (int) $user->expenses()->whereBetween('expense_date', [$start, $end])->sum('amount');

        $categoryTotals = $user->expenses()
            ->selectRaw('category_id, SUM(amount) as amount')
            ->whereBetween('expense_date', [$start, $end])
            ->groupBy('category_id')
            ->pluck('amount', 'category_id');

        // الميزانيات مرتبطة بفترة راتب — المدى المتدحرج لا ميزانية له،
        // فالفرق والنسبة صفر بدل مقارنة مصروف أسبوعين بميزانية شهر.
        $budgets = $window['key'] !== null
            ? $user->budgets()->where('month', $window['key'])->pluck('amount', 'category_id')
            : collect();

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
        $monthly = collect($salaryMonth->lastPeriods(12, $window['anchor']))
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
            'month' => $window['month'],
            'range' => $window['range'],
            'periodLabel' => $window['label'],
            'periodRange' => $window['rangeLabel'],
            'availableMonths' => collect($salaryMonth->lastPeriods(12))
                ->reverse()
                ->map(fn (array $p): array => [
                    'value' => $p['key'],
                    'label' => $p['label'].' '.substr($p['key'], 0, 4),
                ])
                ->values()
                ->all(),
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
