<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * تقرير «وين راحت فلوسك» — نفس أرقام صفحة التقارير، مُجمَّعة في PDF واحد.
 *
 * يعمل على **نافذتين** لا واحدة:
 *  • فترة راتب كاملة (`2026-08`) — تُقارَن بالفترة السابقة، ويُذكر قرار
 *    الفائض. هذه النافذة يولّدها الإقفال تلقائياً في
 *    {@see SalaryMonthService::close()}.
 *  • مدى متدحرج بالأيام (آخر 15 · 30 · 60 يوماً) — يُقارَن بالمدى المكافئ
 *    قبله، ولا قرار فائض فيه لأنه لا يُقفَل.
 *
 * كل مبلغ هنا بالهللات كما يخزّنه بقيّة التطبيق — التحويل للريالات في
 * قالب `resources/views/pdf/report.blade.php` وحده.
 */
final class ReportPdfService
{
    private const MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    /** هللات → نص بالريال بأرقام لاتينية — تنسيق المبالغ في قالب PDF. */
    public static function money(int $halalas): string
    {
        return number_format($halalas / 100, 0).' ر.س';
    }

    /** @param  array<string, mixed>  $window */
    public function render(array $window): Mpdf
    {
        $mpdf = $this->newMpdf();
        $mpdf->SetHTMLFooter(
            '<div style="font-family: thmanyahsans; direction: rtl; text-align: center; '
            .'font-size: 9px; color: #9b9b9b;">'
            .'موفّر — تقرير آلي، لا يُعتمد كمستند محاسبي رسمي · صفحة {PAGENO} من {nbpg}'
            .'</div>'
        );
        $mpdf->WriteHTML(view('pdf.report', $this->build($window))->render());

        return $mpdf;
    }

    /** بايتات تقرير فترة راتب كاملة — جاهزة للتنزيل أو الحفظ. */
    public function output(string $month): string
    {
        return $this->render($this->monthWindow($month))->Output('', Destination::STRING_RETURN);
    }

    /** بايتات تقرير مدى متدحرج بالأيام (15 · 30 · 60). */
    public function outputDays(int $days): string
    {
        return $this->render($this->daysWindow($days))->Output('', Destination::STRING_RETURN);
    }

    /**
     * نافذة فترة راتب — «راتب أغسطس»، ولها سابقةٌ تُقارَن بها وقرار فائض.
     *
     * @return array<string, mixed>
     */
    public function monthWindow(string $month): array
    {
        $salaryMonth = SalaryMonthService::for($this->user);
        $period = $salaryMonth->period($month);
        [$start, $end] = $salaryMonth->rangeFor($month);

        return [
            'key' => $month,
            'label' => $period['label'],
            'range' => $period['range'],
            'start' => $start,
            'end' => $end,
            'period' => $period,
        ];
    }

    /**
     * نافذة مدى متدحرج تنتهي اليوم — «آخر 30 يوم».
     *
     * لا تُقفَل ولا قرار فائض لها، فقسم القرار يسقط من التقرير كلّه بدل أن
     * يُطبع «لم تُقفل الفترة بعد» على مدى لا يُقفَل أصلاً.
     *
     * @return array<string, mixed>
     */
    public function daysWindow(int $days): array
    {
        $end = CarbonImmutable::today()->endOfDay();
        $start = CarbonImmutable::today()->subDays($days - 1)->startOfDay();

        return [
            'key' => null,
            'days' => $days,
            'label' => 'آخر '.$days.' يوم',
            'range' => self::dayLabel($start).' ← '.self::dayLabel($end),
            'start' => $start,
            'end' => $end,
        ];
    }

    /** «27 أغسطس» — أرقام لاتينية وشهر بالعربي، كما في بقيّة التطبيق. */
    public static function dayLabel(CarbonImmutable $date): string
    {
        return $date->day.' '.self::MONTHS[(int) $date->month];
    }

    /**
     * محرّك mpdf — يدعم تشكيل الحروف العربية (OTL) واتجاه BiDi أصالةً،
     * خلافاً لـdompdf الذي جُرِّب قبله ولا يدعم أياً منهما.
     */
    private function newMpdf(): Mpdf
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'thmanyahsans',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts-pdf')]),
            'fontdata' => $fontData + [
                'thmanyahsans' => [
                    'R' => 'thmanyahsans-Regular.ttf',
                    'B' => 'thmanyahsans-Bold.ttf',
                    // OTL: تشكيل حروف عربي حقيقي (اتصال الحروف حسب موضعها).
                    'useOTL' => 0xFF,
                    // Kashida: تمديد الحروف لضبط عرض السطر بدل تباعد الكلمات — عربي أصيل.
                    'useKashida' => 75,
                ],
            ],
            'tempDir' => storage_path('app/mpdf'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    public function build(array $window): array
    {
        $start = $window['start'];
        $end = $window['end'];
        $summary = $this->summarize($window);
        [$previousSummary, $decision] = $this->comparison($window, $summary);

        $categoryTotals = $this->user->expenses()
            ->selectRaw('category_id, SUM(amount) as amount')
            ->whereBetween('expense_date', [$start, $end])
            ->groupBy('category_id')
            ->pluck('amount', 'category_id');

        $categories = $this->user->categories()->orderBy('id')->get()
            ->map(function (Category $category) use ($categoryTotals, $summary): array {
                $amount = (int) ($categoryTotals[$category->id] ?? 0);

                return [
                    'name' => $category->name,
                    'amount' => $amount,
                    'percentage' => $summary['expenses'] > 0
                        ? (int) round(($amount / $summary['expenses']) * 100)
                        : 0,
                ];
            })
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();

        $commitments = collect($this->commitmentRows($window));

        $transactions = $this->user->expenses()->with('category')
            ->whereBetween('expense_date', [$start, $end])
            ->get()
            ->map(fn (Expense $expense): array => [
                'date' => $expense->expense_date->format('Y-m-d'),
                'type' => 'expense',
                'label' => $expense->description ?: ($expense->category?->name ?? 'مصروف'),
                'amount' => -(int) $expense->amount,
            ])
            ->concat(
                $this->user->incomes()
                    ->whereBetween('income_date', [$start, $end])
                    ->get()
                    ->map(fn ($income): array => [
                        'date' => $income->income_date->format('Y-m-d'),
                        'type' => 'income',
                        'label' => $income->description ?: $income->source,
                        'amount' => (int) $income->amount,
                    ])
            )
            ->sortBy('date')
            ->values()
            ->all();

        return [
            'generatedAt' => now()->format('Y-m-d'),
            'user' => $this->user,
            'period' => ['label' => $window['label'], 'range' => $window['range']],
            'summary' => $summary,
            'previousSummary' => $previousSummary,
            'decision' => $decision,
            'categories' => $categories,
            'commitments' => [
                'paid' => (int) $commitments->where('is_paid', true)->sum('amount'),
                'reserved' => (int) $commitments->where('is_paid', false)->sum('amount'),
                'rows' => $commitments->all(),
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * مجاميع النافذة.
     *
     * نافذة فترة الراتب تقرأ من {@see SalaryMonthService::summaryFor()} حرفياً
     * حتى تطابق أرقامُ الـPDF أرقامَ الإقفال ولا يختلف مصدران على رقم واحد.
     * المدى المتدحرج يُحسب بالتواريخ لأنه لا مفتاح فترة له.
     *
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    private function summarize(array $window): array
    {
        if ($window['key'] !== null) {
            return SalaryMonthService::for($this->user)->summaryFor($window['key']);
        }

        $range = [$window['start'], $window['end']];
        $income = (int) $this->user->incomes()->whereBetween('income_date', $range)->sum('amount');
        $expenses = (int) $this->user->expenses()->whereBetween('expense_date', $range)->sum('amount');
        $savings = (int) $this->user->savingsDeposits()->whereBetween('deposited_at', $range)->sum('amount');

        return [
            'key' => null,
            'label' => $window['label'],
            'range' => $window['range'],
            'income' => $income,
            'expenses' => $expenses,
            'commitments' => (int) $this->user->commitmentPayments()
                ->whereBetween('paid_at', $range)
                ->sum('commitment_payments.amount'),
            'savings' => $savings,
            'surplus' => $income - $expenses - $savings,
        ];
    }

    /**
     * ما تُقارَن به النافذة، وقرار الفائض إن كانت فترةً قابلة للإقفال.
     *
     * @param  array<string, mixed>  $window
     * @param  array<string, mixed>  $summary
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function comparison(array $window, array $summary): array
    {
        if ($window['key'] !== null) {
            $salaryMonth = SalaryMonthService::for($this->user);
            $previousKey = $salaryMonth->periodFor($window['period']['startsOn']->subDay())['key'];

            $closed = $this->user->salaryPeriods()
                ->where('period_key', $window['key'])
                ->whereNotNull('closed_at')
                ->first();

            return [
                $salaryMonth->summaryFor($previousKey),
                $this->decisionLabel($closed?->surplus_action, $summary['surplus']),
            ];
        }

        // المدى المكافئ الذي يسبقه مباشرة — 30 يوماً تُقارَن بالثلاثين قبلها.
        $days = (int) $window['days'];
        $previousEnd = $window['start']->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($days - 1)->startOfDay();

        $previous = $this->summarize([
            'key' => null,
            'days' => $days,
            'label' => 'الـ'.$days.' يوم السابقة',
            'range' => self::dayLabel($previousStart).' ← '.self::dayLabel($previousEnd),
            'start' => $previousStart,
            'end' => $previousEnd,
        ]);

        // المدى المتدحرج لا يُقفَل، فلا قرار فائض له — والقسم كلّه يسقط.
        return [$previous, null];
    }

    /**
     * صفوف الالتزامات داخل النافذة — من مولّد الظهورات الموحّد.
     *
     * @param  array<string, mixed>  $window
     * @return list<array<string, mixed>>
     */
    private function commitmentRows(array $window): array
    {
        $service = CommitmentService::for($this->user);
        $salaryMonth = SalaryMonthService::for($this->user);
        $from = $window['start']->format('Y-m-d');
        $to = $window['end']->format('Y-m-d');

        // فترات الراتب التي يتقاطع معها المدى — واحدة للشهر، وقد تكون ثلاثاً
        // لستّين يوماً. المرور عليها كلّها هو ما يجعل كل استحقاق داخل المدى
        // يظهر فيه.
        $periods = [];
        $period = $salaryMonth->periodFor($window['start']);

        while ($period['startsOn']->lessThanOrEqualTo($window['end'])) {
            $periods[] = $period;
            $period = $salaryMonth->periodFor($period['nextSalary']);
        }

        $rows = [];

        foreach ($this->user->commitments()->active()->orderBy('id')->get() as $commitment) {
            foreach ($service->occurrences($commitment, $periods) as $occurrence) {
                if ($occurrence['due_date'] < $from || $occurrence['due_date'] > $to) {
                    continue;
                }

                $rows[] = [
                    'name' => $commitment->name,
                    'due_date' => $occurrence['due_date'],
                    'amount' => $occurrence['amount'],
                    'is_paid' => $occurrence['is_paid'],
                    'status' => match ($occurrence['status']) {
                        CommitmentService::STATUS_PAID => 'مدفوع',
                        CommitmentService::STATUS_OVERDUE => 'متأخّر',
                        default => 'محجوز',
                    },
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));

        return $rows;
    }

    private function decisionLabel(?string $action, int $surplus): string
    {
        if ($surplus <= 0) {
            return 'عجز — لم يُسجَّل قرار عليه.';
        }

        return match ($action) {
            'saved' => 'حُوِّل كاملاً إلى الادخار.',
            'split' => 'نُصفه ادخار ونصفه ترحيل للفترة القادمة.',
            'rolled' => 'رُحِّل كاملاً كدخل للفترة القادمة.',
            default => 'لم تُقفل الفترة بعد.',
        };
    }
}
