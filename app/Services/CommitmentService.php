<?php

namespace App\Services;

use App\Models\Commitment;
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

    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
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
     * تحويل الالتزامات إلى شكل الواجهة مع حقول محسوبة (الاستحقاق · الدفع هذا الشهر).
     *
     * @param  Collection<int,Commitment>  $commitments
     * @return list<array<string,mixed>>
     */
    public function hydrate(Collection $commitments, ?array $period = null): array
    {
        $period ??= $this->currentPeriod();

        return $commitments
            ->map(function (Commitment $c) use ($period): array {
                $payment = $c->payments()->where('period_key', $period['key'])->first();

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
                    'due_date' => $this->dueDateFor($c, $period)->format('Y-m-d'),
                    'reserve_in_budget' => $c->reserve_in_budget,
                    'is_paid_this_month' => $payment !== null,
                    'paid_at' => $payment?->paid_at?->format('Y-m-d'),
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
            $payment = $c->payments()->where('period_key', $period['key'])->first();

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
            $paid = $c->payments()->where('period_key', $period['key'])->exists();

            if ($paid) {
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
            if ($c->payments()->where('period_key', $period['key'])->exists()) {
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
