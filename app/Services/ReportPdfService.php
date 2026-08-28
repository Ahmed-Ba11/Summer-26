<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Commitment;
use App\Models\Expense;
use App\Models\User;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * تقرير «وين راحت فلوسك» — نفس أرقام صفحة التقارير، مُجمَّعة في PDF واحد
 * لأي فترة راتب. يُستدعى يدوياً من الإعدادات أو صفحة التقارير، وتلقائياً
 * عند إقفال شهر الراتب في {@see SalaryMonthService::close()}.
 *
 * كل مبلغ هنا بالهللات كما يخزّنه بقيّة التطبيق — التحويل للريالات في
 * قالب `resources/views/pdf/report.blade.php` وحده.
 */
final class ReportPdfService
{
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

    public function render(string $month): Mpdf
    {
        $mpdf = $this->newMpdf();
        $mpdf->SetHTMLFooter(
            '<div style="font-family: thmanyahsans; direction: rtl; text-align: center; '
            .'font-size: 9px; color: #9b9b9b;">'
            .'موفّر — تقرير آلي، لا يُعتمد كمستند محاسبي رسمي · صفحة {PAGENO} من {nbpg}'
            .'</div>'
        );
        $mpdf->WriteHTML(view('pdf.report', $this->build($month))->render());

        return $mpdf;
    }

    /** بايتات الـPDF جاهزة للتنزيل أو الحفظ. */
    public function output(string $month): string
    {
        return $this->render($month)->Output('', Destination::STRING_RETURN);
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
     * @return array<string, mixed>
     */
    public function build(string $month): array
    {
        $salaryMonth = SalaryMonthService::for($this->user);
        $commitmentService = CommitmentService::for($this->user);

        $period = $salaryMonth->period($month);
        [$start, $end] = $salaryMonth->rangeFor($month);
        $summary = $salaryMonth->summaryFor($month);

        $previousKey = $salaryMonth->periodFor($period['startsOn']->subDay())['key'];
        $previousSummary = $salaryMonth->summaryFor($previousKey);

        $closedPeriod = $this->user->salaryPeriods()
            ->where('period_key', $month)
            ->whereNotNull('closed_at')
            ->first();

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

        $commitments = $this->user->commitments()->active()->orderBy('id')->get()
            ->filter(fn (Commitment $commitment): bool => $commitmentService->hasOccurrence($commitment, $period))
            ->map(function (Commitment $commitment) use ($commitmentService, $period): array {
                $occurrence = $commitmentService->occurrence($commitment, $period);

                return [
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
            })
            ->values();

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
            'period' => $period,
            'summary' => $summary,
            'previousSummary' => $previousSummary,
            'decision' => $this->decisionLabel($closedPeriod?->surplus_action, $summary['surplus']),
            'categories' => $categories,
            'commitments' => [
                'paid' => (int) $commitments->where('is_paid', true)->sum('amount'),
                'reserved' => (int) $commitments->where('is_paid', false)->sum('amount'),
                'rows' => $commitments->all(),
            ],
            'transactions' => $transactions,
        ];
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
