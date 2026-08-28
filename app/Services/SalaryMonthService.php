<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SalaryPeriod;
use App\Models\SavingsGoal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * شهر الراتب — مصدر الحقيقة الوحيد لحدود الفترة في الخادم.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  شهر المستخدم يبدأ يوم نزول راتبه لا يوم 1 من التقويم.
 * ══════════════════════════════════════════════════════════════════════
 *
 * راتب يوم 27 يعني أن فترة `2026-08` تمتد من 2026-08-27 إلى 2026-09-26.
 * بدون هذا، «المتبقي لك» و«الحد اليومي الآمن» يُصفَّران في منتصف شهر
 * المستخدم فيصيران غلطاً معظم أيام الشهر لأي موظّف راتبه بعد يوم 20.
 *
 * من راتبه يوم 1 لا يلاحظ أي فرق — الفترة تطابق الشهر التقويمي تماماً.
 *
 * المصطلح المعروض دائماً «راتب أغسطس». كلمة «دورة» ممنوعة.
 *
 * ⚠️ نظيره في الواجهة `resources/js/lib/money.ts` — للعرض الفوري فقط.
 *
 * كل المبالغ بالهللات (integer).
 */
final class SalaryMonthService
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

    // ═══════════════════════════════════════════════════════════════════
    //  الفترات
    // ═══════════════════════════════════════════════════════════════════

    /**
     * فترة الراتب التي يقع فيها اليوم.
     *
     * @return array{key:string, startsOn:CarbonImmutable, endsOn:CarbonImmutable, salaryDate:CarbonImmutable, nextSalary:CarbonImmutable, label:string, range:string, daysLeft:int, dayIndex:int, totalDays:int}
     */
    public function current(): array
    {
        return $this->periodFor(CarbonImmutable::today());
    }

    /** الفترة السابقة لفترة اليوم. */
    public function previous(): array
    {
        return $this->periodFor($this->current()['startsOn']->subDay());
    }

    /** الفترة التالية لفترة اليوم. */
    public function next(): array
    {
        return $this->periodFor($this->current()['nextSalary']);
    }

    /** فترة الراتب التي يقع فيها تاريخ معيّن. */
    public function periodFor(CarbonImmutable|string|null $date = null): array
    {
        $day = $this->toDate($date);
        $start = $this->salaryDateIn($day);

        if ($start->greaterThan($day)) {
            $start = $this->salaryDateIn($day->startOfMonth()->subMonth());
        }

        return $this->describe($start);
    }

    /** الفترة صاحبة هذا المفتاح (`2026-08`). */
    public function period(string $key): array
    {
        return $this->describe($this->startOf($key));
    }

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable} بداية الفترة ونهايتها */
    public function boundsFor(string $key): array
    {
        $start = $this->startOf($key);

        return [$start, $this->nextSalaryAfter($start)->subDay()];
    }

    /**
     * حدود الفترة كأوقات كاملة.
     *
     * الطرف الأعلى نهاية اليوم لا بدايته: أعمدة التواريخ مخزَّنة كـdatetime،
     * ومقارنة `2026-09-26 00:00:00` بـ`2026-09-26` تُسقط يوم كامل من الفترة.
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    public function rangeFor(string $key): array
    {
        [$start, $end] = $this->boundsFor($key);

        return [$start->startOfDay(), $end->endOfDay()];
    }

    /** مفتاح فترة الراتب لأي تاريخ. */
    public function keyFor(CarbonImmutable|string|null $date = null): string
    {
        return $this->periodFor($date)['key'];
    }

    /** «راتب أغسطس» لمفتاح فترة. */
    public function labelFor(string $key): string
    {
        return 'راتب '.self::MONTHS[(int) substr($key, 5, 2)];
    }

    /**
     * آخر عدد من الفترات المنتهية بفترة معيّنة — للاتجاه في اللوحة والتقارير.
     *
     * @return list<array{key:string, label:string, startsOn:CarbonImmutable, endsOn:CarbonImmutable}>
     */
    public function lastPeriods(int $count, ?string $endingAt = null): array
    {
        $start = $this->startOf($endingAt ?? $this->current()['key']);
        $periods = [];

        for ($back = $count - 1; $back >= 0; $back--) {
            $anchor = $this->salaryDateIn($start->startOfMonth()->subMonths($back));

            $periods[] = [
                'key' => $anchor->format('Y-m'),
                'label' => 'راتب '.self::MONTHS[(int) $anchor->month],
                'startsOn' => $anchor,
                'endsOn' => $this->nextSalaryAfter($anchor)->subDay(),
            ];
        }

        return $periods;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  مجاميع الفترة
    // ═══════════════════════════════════════════════════════════════════

    public function incomeFor(string $key): int
    {
        return (int) $this->user->incomes()
            ->whereBetween('income_date', $this->rangeFor($key))
            ->sum('amount');
    }

    public function expensesFor(string $key): int
    {
        return (int) $this->user->expenses()
            ->whereBetween('expense_date', $this->rangeFor($key))
            ->sum('amount');
    }

    /** ما دُفع فعلاً من الالتزامات في الفترة. */
    public function commitmentsFor(string $key): int
    {
        return (int) $this->user->commitmentPayments()
            ->where('period_key', $key)
            ->sum('commitment_payments.amount');
    }

    /** صافي ما أُودع في أهداف الادخار خلال الفترة. */
    public function savingsFor(string $key): int
    {
        return (int) $this->user->savingsDeposits()
            ->where('period_key', $key)
            ->sum('amount');
    }

    /**
     * الفائض = الدخل − (المصروف + الادخار).
     * الالتزامات المدفوعة مسجَّلة أصلاً كمصروفات، فلا تُطرح مرتين.
     */
    public function surplusFor(string $key): int
    {
        return $this->incomeFor($key) - $this->expensesFor($key) - $this->savingsFor($key);
    }

    /** @return array{key:string, label:string, range:string, income:int, expenses:int, commitments:int, savings:int, surplus:int} */
    public function summaryFor(string $key): array
    {
        $period = $this->period($key);

        return [
            'key' => $key,
            'label' => $period['label'],
            'range' => $period['range'],
            'income' => $this->incomeFor($key),
            'expenses' => $this->expensesFor($key),
            'commitments' => $this->commitmentsFor($key),
            'savings' => $this->savingsFor($key),
            'surplus' => $this->surplusFor($key),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  الإقفال
    // ═══════════════════════════════════════════════════════════════════

    /**
     * ملخّص الفترة السابقة إن لم تُقفل بعد — وإلا `null`.
     *
     * الفائض **لا يُرحَّل صامتاً**: الترحيل الصامت يضخّم «المتبقي لك»
     * فيتحوّل الفائض إلى صرف بدل ادخار. لذلك يُسأل المستخدم صراحة.
     */
    public function pendingClose(): ?array
    {
        $previous = $this->previous();

        $closed = $this->user->salaryPeriods()
            ->where('period_key', $previous['key'])
            ->whereNotNull('closed_at')
            ->exists();

        if ($closed) {
            return null;
        }

        $summary = $this->summaryFor($previous['key']);

        // لا شيء يُقفَل لمن لم يكن يستعمل التطبيق في تلك الفترة.
        if ($summary['income'] === 0 && $summary['expenses'] === 0) {
            return null;
        }

        return [
            ...$summary,
            'nextLabel' => $this->current()['label'],
            'goals' => $this->openGoals(),
        ];
    }

    /** أهداف الادخار المفتوحة — وجهة الفائض المقترحة. */
    public function openGoals(): array
    {
        return $this->user->savingsGoals()
            ->where('is_completed', false)
            ->where('is_closed', false)
            ->orderBy('target_date')
            ->get()
            ->map(fn (SavingsGoal $goal): array => [
                'id' => $goal->id,
                'name' => $goal->name,
                'icon' => $goal->icon ?: 'piggy-bank',
                'remaining' => max(0, (int) $goal->target_amount - (int) $goal->current_amount),
            ])
            ->values()
            ->all();
    }

    /**
     * إقفال فترة راتب وتوجيه فائضها.
     *
     * `saved`  → كامل الفائض إلى هدف ادخار.
     * `rolled` → دخل باسم «فائض راتب أغسطس» في أول الفترة الجديدة.
     * `split`  → النصف ادخاراً والنصف ترحيلاً.
     *
     * العجز (فائض سالب) يُسجَّل كما هو ولا يُنشئ أي حركة.
     *
     * @throws ValidationException
     */
    public function close(string $key, string $action, ?int $savingsGoalId = null): SalaryPeriod
    {
        if (! in_array($action, ['saved', 'rolled', 'split'], true)) {
            throw ValidationException::withMessages(['action' => 'خيار غير معروف.']);
        }

        $summary = $this->summaryFor($key);
        $surplus = $summary['surplus'];
        $goal = null;

        if ($surplus > 0 && in_array($action, ['saved', 'split'], true)) {
            $goal = $this->user->savingsGoals()
                ->where('is_closed', false)
                ->find($savingsGoalId);

            if (! $goal) {
                throw ValidationException::withMessages([
                    'savings_goal_id' => 'اختر هدف ادخار يستقبل الفائض.',
                ]);
            }
        }

        [$start, $end] = $this->boundsFor($key);
        $nextStart = $end->addDay();

        $period = DB::transaction(function () use ($key, $action, $summary, $surplus, $goal, $start, $end, $nextStart): SalaryPeriod {
            $period = $this->user->salaryPeriods()->updateOrCreate(
                ['period_key' => $key],
                [
                    'starts_on' => $start->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'income_total' => $summary['income'],
                    'expenses_total' => $summary['expenses'],
                    'commitments_total' => $summary['commitments'],
                    'savings_total' => $summary['savings'],
                    'surplus' => $surplus,
                    'surplus_action' => $action,
                    'closed_at' => now(),
                ],
            );

            if ($surplus <= 0) {
                return $period;
            }

            $toSavings = match ($action) {
                'saved' => $surplus,
                'split' => intdiv($surplus, 2),
                default => 0,
            };
            $toRoll = $surplus - $toSavings;

            if ($toSavings > 0 && $goal) {
                SavingsLedger::for($this->user)->deposit($goal, $toSavings, $end->toDateString());
            }

            if ($toRoll > 0) {
                $this->user->incomes()->create([
                    'amount' => $toRoll,
                    'source' => 'فائض '.$summary['label'],
                    'description' => 'فائض مُرحَّل من '.$summary['label'],
                    'income_date' => $nextStart->toDateString(),
                    'is_recurring' => false,
                ]);
            }

            return $period;
        });

        Storage::disk('local')->put(
            "reports/{$this->user->id}/{$key}.pdf",
            ReportPdfService::for($this->user)->output($key),
        );

        return $period;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  أدوات
    // ═══════════════════════════════════════════════════════════════════

    /** يوم الراتب مقيَّد 1..28 حتى تبقى كل الشهور قابلة للتمثيل. */
    public function salaryDay(): int
    {
        return SalaryPeriod::salaryDay($this->user);
    }

    private function describe(CarbonImmutable $start): array
    {
        $nextSalary = $this->nextSalaryAfter($start);
        $end = $nextSalary->subDay();
        $today = CarbonImmutable::today();
        $totalDays = (int) $start->diffInDays($nextSalary);

        $daysLeft = match (true) {
            $today->greaterThan($end) => 0,
            $today->lessThan($start) => $totalDays,
            default => (int) $today->diffInDays($nextSalary),
        };

        $dayIndex = match (true) {
            $today->greaterThan($end) => $totalDays,
            $today->lessThan($start) => 0,
            default => (int) $start->diffInDays($today) + 1,
        };

        return [
            'key' => $start->format('Y-m'),
            'startsOn' => $start,
            'endsOn' => $end,
            'salaryDate' => $start,
            'nextSalary' => $nextSalary,
            'label' => 'راتب '.self::MONTHS[(int) $start->month],
            'range' => sprintf(
                '%d %s ← %d %s',
                $start->day,
                self::MONTHS[(int) $start->month],
                $end->day,
                self::MONTHS[(int) $end->month],
            ),
            'daysLeft' => $daysLeft,
            'dayIndex' => $dayIndex,
            'totalDays' => $totalDays,
        ];
    }

    private function startOf(string $key): CarbonImmutable
    {
        return $this->salaryDateIn(
            CarbonImmutable::createFromFormat('!Y-m-d', $key.'-01')
        );
    }

    private function nextSalaryAfter(CarbonImmutable $start): CarbonImmutable
    {
        return $this->salaryDateIn($start->startOfMonth()->addMonth());
    }

    private function salaryDateIn(CarbonImmutable $anchor): CarbonImmutable
    {
        $anchor = $anchor->startOfDay();

        return $anchor->setDay(min($this->salaryDay(), $anchor->daysInMonth));
    }

    private function toDate(CarbonImmutable|string|null $date): CarbonImmutable
    {
        if ($date instanceof CarbonImmutable) {
            return $date->startOfDay();
        }

        return $date === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse($date)->startOfDay();
    }
}
