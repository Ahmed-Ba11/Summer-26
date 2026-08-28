<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * منطق الالتزامات — مصدر واحد للحقيقة في الخادم.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  «محجوز» مقابل «مدفوع»:
 *   • محجوز: مبلغ محدَّد لم يُدفع بعد → يُطرح من المتبقي، والفلوس فيه.
 *   • مدفوع: خرج فعلاً → يُحصى في شريط الصحة.
 *   • الفاتورة المتغيّرة تُحجز بمتوسّط آخر 3 أشهر لا بصفر.
 * ══════════════════════════════════════════════════════════════════════
 */
final class CommitmentService
{
    private const KIND_ICON = [
        'bill' => 'receipt', 'rent' => 'house', 'installment' => 'credit-card', 'subscription' => 'repeat',
    ];

    private const KIND_COLOR = [
        'bill' => 'var(--chart-7)', 'rent' => 'var(--chart-5)', 'installment' => 'var(--chart-2)', 'subscription' => 'var(--chart-3)',
    ];

    /**
     * الحالات الثلاث لظهور واحد لالتزام في فترة راتب واحدة.
     *
     * «الظهور» لا «الالتزام»: اشتراك النت يوم 25 ظهورٌ في كل فترة، ولكل
     * ظهور حالته الخاصة — مسدَّد في أغسطس وقادم في سبتمبر. الحالة تُقرأ من
     * `commitment_payments` بمفتاح الفترة، لا من تاريخ الاستحقاق وحده:
     * التاريخ يقول متى يُستحق، والجدول وحده يقول هل دُفع.
     */
    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_UPCOMING = 'upcoming';

    /**
     * دفعات كل التزام مفهرسة بمفتاح الفترة — استعلام واحد لكل التزام
     * بدل استعلام لكل ظهور، فالتقويم يعرض التزاماً عبر فترتين في الشاشة.
     *
     * @var array<int, array<string, CommitmentPayment>>
     */
    private array $payments = [];

    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    /** دفعة هذا الالتزام في هذه الفترة — أو `null` إن لم يُسدَّد. */
    public function paymentFor(Commitment $commitment, string $periodKey): ?CommitmentPayment
    {
        $this->payments[$commitment->id] ??= $commitment->payments()
            ->get()
            ->keyBy('period_key')
            ->all();

        return $this->payments[$commitment->id][$periodKey] ?? null;
    }

    /** حالة ظهور واحد: مسدَّد · متأخّر · قادم. */
    public function statusFor(Commitment $commitment, array $period): string
    {
        if ($this->paymentFor($commitment, $period['key']) !== null) {
            return self::STATUS_PAID;
        }

        return $this->dueDateFor($commitment, $period)->lessThan(CarbonImmutable::today())
            ? self::STATUS_OVERDUE
            : self::STATUS_UPCOMING;
    }

    /**
     * الظهور كاملاً — الشكل الذي تعرضه كل الواجهات.
     *
     * @return array{period_key:string, due_date:string, status:string, is_paid:bool, paid_at:?string, amount:int}
     */
    public function occurrence(Commitment $commitment, array $period): array
    {
        $payment = $this->paymentFor($commitment, $period['key']);

        return [
            'period_key' => $period['key'],
            'due_date' => $this->dueDateFor($commitment, $period)->format('Y-m-d'),
            'status' => $this->statusFor($commitment, $period),
            'is_paid' => $payment !== null,
            'paid_at' => $payment?->paid_at?->format('Y-m-d'),
            // المسدَّد يُعرض بما دُفع فعلاً لا بالمتوقَّع — الفاتورة المتغيّرة
            // تختلف عن متوسّطها، وعرض المتوسّط بعد الدفع يخالف كشف الحساب.
            'amount' => $payment !== null
                ? (int) $payment->amount
                : $this->expectedAmount($commitment),
        ];
    }

    /**
     * تسجيل سداد التزام من مصروف — فترة السداد من تاريخ المصروف.
     *
     * المصروف المرتبط بالتزام سدادٌ له، فلا بدّ أن يكتب صفّاً في
     * `commitment_payments`؛ وإلا بقي الالتزام «متأخّراً» في التقويم
     * واللوحة وقد خرج المال فعلاً.
     *
     * الفترة من `expense_date` لا من اليوم: من يسجّل فاتورة الشهر الماضي
     * متأخّراً يجب أن تُقيَّد في فترتها هي.
     */
    public function recordPaymentFromExpense(Commitment $commitment, Expense $expense): ?CommitmentPayment
    {
        $periodKey = SalaryMonthService::for($this->user)
            ->keyFor(CarbonImmutable::parse($expense->expense_date));

        // القيد الفريد (commitment_id, period_key) يمنع سدادين لفترة واحدة —
        // نحترمه هنا صراحةً بدل أن نصطدم به.
        if ($this->paymentFor($commitment, $periodKey) !== null) {
            return null;
        }

        $payment = $commitment->payments()->create([
            'amount' => (int) $expense->amount,
            'paid_at' => $expense->expense_date->toDateString(),
            'period_key' => $periodKey,
            'source' => 'manual',
        ]);

        if ($commitment->kind === 'installment' && $commitment->months_count > 0) {
            $paid = min($commitment->months_count, $commitment->months_paid + 1);
            $commitment->update([
                'months_paid' => $paid,
                'is_active' => $paid < $commitment->months_count,
            ]);
        }

        $this->forgetPayments($commitment);

        return $payment;
    }

    /** حذف المصروف يسحب سداده — وإلا بقي الالتزام مسدَّداً بلا مال خرج. */
    public function revokePaymentFromExpense(Commitment $commitment, Expense $expense): void
    {
        $periodKey = SalaryMonthService::for($this->user)
            ->keyFor(CarbonImmutable::parse($expense->expense_date));

        $payment = $commitment->payments()->where('period_key', $periodKey)->first();

        if ($payment === null) {
            return;
        }

        if ($commitment->kind === 'installment' && $commitment->months_paid > 0) {
            $commitment->update([
                'months_paid' => max(0, $commitment->months_paid - 1),
                'is_active' => true,
            ]);
        }

        $payment->delete();
        $this->forgetPayments($commitment);
    }

    /** يُبطل ذاكرة الدفعات بعد كتابة أو حذف دفعة في نفس الطلب. */
    public function forgetPayments(?Commitment $commitment = null): void
    {
        if ($commitment === null) {
            $this->payments = [];

            return;
        }

        unset($this->payments[$commitment->id]);
    }

    /**
     * فترة الراتب الحالية = من نزول آخر راتب إلى اليوم قبل راتب الشهر التالي.
     *
     * الحساب كلّه في `SalaryMonthService` — هنا استعمال فقط، حتى لا تختلف
     * حدود الفترة بين وحدة وأخرى.
     *
     * @return array{key:string, salaryDate:CarbonImmutable, nextSalary:CarbonImmutable, label:string, range:string}
     */
    public function currentPeriod(): array
    {
        return SalaryMonthService::for($this->user)->current();
    }

    /**
     * الفترة التي يخصّها سداد اليوم.
     *
     * ظهور الالتزام داخل نافذة الراتب الحالية قد يكون في المستقبل: فاتورة
     * يوم 25 ونافذة الراتب تبدأ يوم 27 → أقرب ظهور داخل النافذة هو 25 من
     * الشهر القادم، بينما الظهور المستحقّ فعلاً هو ظهور الفترة السابقة
     * الذي مضى موعده ولم يُدفع.
     *
     * نسبة السداد لفترة اليوم في هذه الحالة تقلب الواقع رأساً على عقب:
     * المنقضي يبقى «فات موعده» والمستقبلي يصير «تم السداد». فالسداد
     * يُنسب لأقدم ظهور مضى موعده ولم يُدفع، وإلا فلفترة اليوم.
     */
    public function payablePeriod(Commitment $commitment): array
    {
        $current = $this->currentPeriod();
        $previous = SalaryMonthService::for($this->user)
            ->periodFor($current['startsOn']->subDay());

        $previousDue = $this->dueDateFor($commitment, $previous);

        // لا يُنسب سداد لظهور سابق لوجود الالتزام نفسه
        $existedThen = $commitment->created_at === null
            || CarbonImmutable::parse($commitment->created_at)->startOfDay()
                ->lessThanOrEqualTo($previousDue);

        if ($existedThen
            && $previousDue->lessThanOrEqualTo(CarbonImmutable::today())
            && $this->paymentFor($commitment, $previous['key']) === null) {
            return $previous;
        }

        return $current;
    }

    /**
     * تحويل الالتزامات إلى شكل الواجهة مع حقول محسوبة (الاستحقاق · الدفع هذا الشهر).
     *
     * بلا فترة صريحة يُعرض **الظهور القابل للسداد** لكل التزام على حدة، لا
     * ظهور نافذة اليوم: وإلّا عُرض استحقاق مستقبلي وبقي المنقضي غير المدفوع
     * غائباً عن الصفحة، فلا سبيل لتسويته أصلاً.
     *
     * @param  Collection<int,Commitment>  $commitments
     * @return list<array<string,mixed>>
     */
    public function hydrate(Collection $commitments, ?array $period = null): array
    {
        return $commitments
            ->map(function (Commitment $c) use ($period): array {
                $occurrence = $this->occurrence($c, $period ?? $this->payablePeriod($c));

                return [
                    'id' => $c->id,
                    'kind' => $c->kind,
                    'name' => $c->name,
                    'icon' => $c->icon ?: self::KIND_ICON[$c->kind],
                    'color' => $c->color ?: self::KIND_COLOR[$c->kind],
                    'amount' => $c->amount,
                    'is_variable' => $c->is_variable,
                    'average_amount' => $this->averageAmount($c),
                    'total_amount' => (int) $c->total_amount,
                    'months_count' => (int) $c->months_count,
                    'months_paid' => (int) $c->months_paid,
                    'payment_method' => $c->payment_method,
                    'due_type' => $c->due_type,
                    // `due_day` و`notify_when` يلزمان نموذج التعديل: بدونهما
                    // يفتح بقيم افتراضية فيدوس يوم الاستحقاق والتنبيه عند الحفظ.
                    'due_day' => $c->due_day !== null ? (int) $c->due_day : null,
                    'notify_when' => $c->notify_when,
                    'due_date' => $occurrence['due_date'],
                    'reserve_in_budget' => $c->reserve_in_budget,
                    // الحالة تُحسب في الخادم من `commitment_payments` — الواجهة
                    // تعرضها ولا تعيد اشتقاقها من التاريخ.
                    'period_key' => $occurrence['period_key'],
                    'status' => $occurrence['status'],
                    'is_paid_this_month' => $occurrence['is_paid'],
                    'paid_at' => $occurrence['paid_at'],
                ];
            })
            ->values()
            ->all();
    }

    /** دخل فترة الراتب الحالية (بدل التقدير بالدخل المتكرر إن لم يُسجَّل). */
    public function periodIncome(array $period): int
    {
        $income = SalaryMonthService::for($this->user)->incomeFor($period['key']);

        if ($income === 0) {
            $income = (int) $this->user->recurringTransactions()
                ->where('type', 'income')->where('is_active', true)->sum('amount');
        }

        return $income;
    }

    /** المبلغ المتوقّع لهذا الشهر (المتغيّر بلا مبلغ = متوسطه). */
    public function expectedAmount(Commitment $c): int
    {
        if ($c->amount !== null) {
            return (int) $c->amount;
        }

        return $c->is_variable ? $this->averageAmount($c) : 0;
    }

    /**
     * مساهمة الالتزامات في «المحجوز/المدفوع» لفترة الراتب:
     * المدفوعات الفعلية + غير المدفوع ممن reserve_in_budget=true (بمتوسطه للمتغيّر).
     */
    public function obligationsForPeriod(?array $period = null): int
    {
        $period ??= $this->currentPeriod();
        $total = 0;

        foreach ($this->user->commitments()->get() as $c) {
            $payment = $this->paymentFor($c, $period['key']);

            if ($payment) {
                $total += (int) $payment->amount;

                continue;
            }

            if (! $c->reserve_in_budget) {
                continue;
            }

            if (! $c->is_active) {
                continue;
            }

            $total += $this->expectedAmount($c);
        }

        return $total;
    }

    /** «نصيب الالتزامات» لحوض التدفّق في لوحة التحكم — فقط غير المدفوع المحجوز. */
    public function reservedForPeriod(?array $period = null): int
    {
        $period ??= $this->currentPeriod();
        $total = 0;

        foreach ($this->user->commitments()->active()->get() as $c) {
            if ($this->paymentFor($c, $period['key']) !== null) {
                continue;
            }

            if (! $c->reserve_in_budget) {
                continue;
            }

            $total += $this->expectedAmount($c);
        }

        return $total;
    }

    /** مجموع المدفوعات الفعلية في فترة الراتب الحالية. */
    public function paidForPeriod(?array $period = null): int
    {
        $period ??= $this->currentPeriod();

        return (int) $this->user->commitmentPayments()
            ->where('period_key', $period['key'])
            ->sum('commitment_payments.amount');
    }

    /** عدد الالتزامات غير المدفوعة — المتأخّرة وما يستحق قريباً. */
    public function dueSoonCount(int $days = 7, ?array $period = null): int
    {
        $period ??= $this->currentPeriod();
        $horizon = CarbonImmutable::today()->addDays($days);
        $count = 0;

        foreach ($this->user->commitments()->active()->get() as $c) {
            if ($this->statusFor($c, $period) === self::STATUS_PAID) {
                continue;
            }

            if ($this->dueDateFor($c, $period)->lessThanOrEqualTo($horizon)) {
                $count++;
            }
        }

        return $count;
    }

    /** متوسّط آخر 3 دفعات للالتزام. */
    public function averageAmount(Commitment $c): int
    {
        $amounts = $c->payments()->orderByDesc('paid_at')->limit(3)->pluck('amount');

        return $amounts->isEmpty() ? 0 : (int) round($amounts->avg());
    }

    /** تاريخ استحقاق الالتزام ضمن فترة راتب معيّنة. */
    public function dueDateFor(Commitment $c, array $period): CarbonImmutable
    {
        $dueDate = match ($c->due_type) {
            'salary_day' => $period['salaryDate']->copy(),
            'month_day' => $this->dayWithinWindow((int) ($c->due_day ?? 1), $period),
            default => $period['salaryDate']->copy(),
        };

        return $dueDate;
    }

    /** مفتاح فترة الراتب لأي تاريخ — يُخزَّن في commitment_payments.period_key. */
    public function periodKeyFor(CarbonImmutable $date): string
    {
        return SalaryMonthService::for($this->user)->keyFor($date);
    }

    private function dayWithinWindow(int $day, array $period): CarbonImmutable
    {
        $candidate = $period['salaryDate']->setDay(min($day, $period['salaryDate']->daysInMonth));

        if ($candidate->lessThan($period['salaryDate'])) {
            $nextMonth = $period['salaryDate']->addMonth();
            $candidate = $nextMonth->setDay(min($day, $nextMonth->daysInMonth));
        }

        if ($candidate->greaterThanOrEqualTo($period['nextSalary'])) {
            $candidate = $period['salaryDate']->setDay(min($day, $period['salaryDate']->daysInMonth));
        }

        return $candidate;
    }
}
