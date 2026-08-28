<?php

namespace App\Http\Controllers;

use App\Services\CommitmentService;
use App\Services\SalaryMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    private const MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = $this->resolveMonth($request->query('month'));
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $events = collect();

        /**
         * الالتزامات — من جدول `commitments` وبنفس حساب صفحة الالتزامات.
         *
         * كان التقويم يقرأ من `bills` و`installments` القديمين، وقد صار
         * `commitments` المصدر الوحيد منذ ترحيل 2026-08-24. فكل التزام
         * أُضيف بعدها يظهر في صفحة الالتزامات «يستحق بعد 23 يوم» ولا أثر
         * له في التقويم — نصّان يتناقضان لأنهما يقرآن من جدولين.
         *
         * والتاريخ نفسه يُحسب هنا بـ`CommitmentService::dueDateFor` على
         * فترة الراتب، لا بحساب مستقلّ على حدود الشهر التقويمي: الالتزام
         * المربوط بيوم الراتب يتحرّك مع الراتب، وحسابه على يوم 1 يزيحه.
         */
        $service = CommitmentService::for($user);
        $commitments = $user->commitments()->active()->orderBy('name')->get();

        $periods = $this->periodsOverlapping($user, $start, $end);

        foreach ($commitments as $commitment) {
            // مولّد الظهورات نفسه الذي تقرأ منه صفحة الالتزامات — فلا
            // يعرض التقويم استحقاقاً تنكره الصفحة ولا العكس.
            foreach ($service->occurrences($commitment, $periods) as $occurrence) {
                if ($occurrence['due_date'] < $start->format('Y-m-d')
                    || $occurrence['due_date'] > $end->format('Y-m-d')) {
                    continue;
                }

                $events->push([
                    'id' => $commitment->id,
                    'date' => $occurrence['due_date'],
                    'kind' => $commitment->kind === 'installment' ? 'installment' : 'bill',
                    'label' => $commitment->name,
                    'amount' => $occurrence['amount'],
                    // حالة هذا الظهور وحده — لا حالة الالتزام عموماً
                    'periodKey' => $occurrence['period_key'],
                    'status' => $occurrence['status'],
                    'isPaid' => $occurrence['is_paid'],
                    'paidAt' => $occurrence['paid_at'],
                    'canPay' => ! $occurrence['is_paid'],
                    'editUrl' => '/commitments',
                ]);
            }
        }

        $salaryAmount = (int) $user->incomes()->where('is_recurring', true)->sum('amount');
        if ($salaryAmount > 0) {
            $salaryDay = (int) ($user->salary_day ?? 27);
            $events->push([
                'id' => null,
                'date' => ($salaryDay === 0 ? $end : $start->setDay(min($salaryDay, $start->daysInMonth)))->format('Y-m-d'),
                'kind' => 'salary',
                'label' => 'الراتب',
                'amount' => $salaryAmount,
                'isPaid' => false,
                'canPay' => false,
                'editUrl' => null,
            ]);
        }

        $user->savingsDeposits()
            ->with('savingsGoal')
            ->whereBetween('deposited_at', [$start, $end])
            ->orderBy('deposited_at')
            ->get()
            ->each(function ($deposit) use ($events): void {
                $events->push([
                    'id' => $deposit->id,
                    'date' => $deposit->deposited_at->format('Y-m-d'),
                    'kind' => 'savings',
                    'label' => 'إيداع ادخار'.($deposit->savingsGoal?->name ? ' — '.$deposit->savingsGoal->name : ''),
                    'amount' => (int) $deposit->amount,
                    'isPaid' => true,
                    'canPay' => false,
                    'editUrl' => '/savings',
                ]);
            });

        return Inertia::render('Calendar', [
            'month' => $month,
            'monthLabel' => self::MONTHS[(int) $start->month].' '.$start->year,
            'previousMonth' => $start->subMonth()->format('Y-m'),
            'nextMonth' => $start->addMonth()->format('Y-m'),
            'events' => $events->sortBy(fn (array $event): string => $event['date'].'-'.$event['kind'])->values()->all(),
        ]);
    }

    /**
     * فترات الراتب التي تتقاطع مع الشهر التقويمي المعروض — واحدة أو اثنتان.
     *
     * الشهر التقويمي والشهر الراتبي لا ينطبقان: عرض «أغسطس» يقع جزؤه الأول
     * في فترة راتب يوليو وجزؤه الأخير في فترة أغسطس، ولكل فترة استحقاقاتها
     * وحالة دفعها. المرور على الفترتين معاً هو ما يجعل كل استحقاق داخل
     * الشهر المعروض يظهر فيه.
     *
     * @return list<array<string, mixed>>
     */
    private function periodsOverlapping($user, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $salaryMonth = SalaryMonthService::for($user);
        $periods = [];
        $period = $salaryMonth->periodFor($start);

        while ($period['startsOn']->lessThanOrEqualTo($end)) {
            $periods[] = $period;
            $period = $salaryMonth->periodFor($period['nextSalary']);
        }

        return $periods;
    }

    private function resolveMonth(mixed $requested): string
    {
        $month = is_string($requested) ? $requested : null;

        if ($month
            && preg_match('/^\d{4}-\d{2}$/', $month)
            && checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
            return $month;
        }

        return now()->format('Y-m');
    }
}
