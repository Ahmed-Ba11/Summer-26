<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * حارس الميزانية — التحقّق المالي الملزم.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  المبدأ الحاكم: الحقائق تُسجَّل، والخطط تُراجَع.
 * ══════════════════════════════════════════════════════════════════════
 *
 *  • المصروف واقعة حصلت — لا يُمنع أبداً. الحارس يحسب الأثر ويرجّعه
 *    للواجهة كتحذير، لكنه لا يرمي استثناءً.
 *  • الميزانية والادخار والالتزامات قرارات مستقبلية — تُمنع إذا كانت
 *    مستحيلة حسابياً.
 *
 *  كل المبالغ بالهللات (integer).
 *
 *  ⚠️ نظيره في الواجهة هو resources/js/lib/money-rules.ts. القواعد هنا هي
 *     الملزمة؛ ملف الواجهة للعرض الفوري فقط. لا تغيّر أحدهما دون الآخر.
 */
final class BudgetGuard
{
    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  السياق المالي
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return array{
     *   monthlyIncome:int, obligations:int, spent:int,
     *   budgetedTotal:int, capacity:int, available:int, unallocated:int
     * }
     */
    public function context(?string $month = null): array
    {
        $salaryMonth = SalaryMonthService::for($this->user);
        $month ??= $salaryMonth->current()['key'];

        $income = $salaryMonth->incomeFor($month);

        // لو ما فيه دخل مسجّل في شهر الراتب هذا، نرجع للدخل المتكرر كتقدير —
        // وإلا كل تحقّق في بداية الشهر بيفشل بلا سبب حقيقي.
        if ($income === 0) {
            $income = (int) $this->user->recurringTransactions()
                ->where('type', 'income')->where('is_active', true)->sum('amount');
        }

        $spent = $salaryMonth->expensesFor($month);

        $obligations = $this->obligations();

        $budgetedTotal = (int) $this->user->budgets()
            ->where('month', $month)->sum('amount');

        $capacity = max(0, $income - $obligations);

        return [
            'monthlyIncome' => $income,
            'obligations' => $obligations,
            'spent' => $spent,
            'budgetedTotal' => $budgetedTotal,
            'capacity' => $capacity,
            'available' => $income - $obligations - $spent,
            'unallocated' => $capacity - $budgetedTotal,
        ];
    }

    /** التزامات فترة الراتب (مدفوعات + محجوز) + الادخار الشهري المطلوب. */
    private function obligations(): int
    {
        $commitments = CommitmentService::for($this->user)->obligationsForPeriod();

        $savings = (int) $this->user->savingsGoals()
            ->where('is_completed', false)
            ->whereNotNull('target_date')
            ->get()
            ->sum(function ($goal): float {
                $remaining = max(0, (int) $goal->target_amount - (int) $goal->current_amount);
                $months = max(1, (int) now()->diffInMonths($goal->target_date, false));

                return $remaining / $months;
            });

        return $commitments + $savings;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ١ · المصروف — يُسجَّل دائماً، ويُرجَّع أثره للواجهة
    // ═══════════════════════════════════════════════════════════════════

    /**
     * لا يرمي استثناءً أبداً. يرجّع تحذيرات لتعرضها الواجهة بعد الحفظ.
     *
     * @return list<array{severity:string, title:string, detail:?string}>
     */
    public function inspectExpense(int $amount, ?int $categoryId, ?string $month = null): array
    {
        $salaryMonth = SalaryMonthService::for($this->user);
        $month ??= $salaryMonth->current()['key'];
        $ctx = $this->context($month);
        $warnings = [];

        if ($categoryId !== null) {
            $budget = (int) Budget::query()
                ->where('user_id', $this->user->id)
                ->where('category_id', $categoryId)
                ->where('month', $month)
                ->value('amount');

            if ($budget > 0) {
                $spent = (int) $this->user->expenses()
                    ->where('category_id', $categoryId)
                    ->whereBetween('expense_date', $salaryMonth->rangeFor($month))
                    ->sum('amount');

                if ($spent > $budget) {
                    $warnings[] = [
                        'severity' => 'warn',
                        'title' => 'تجاوزت ميزانية هذه الفئة',
                        'detail' => 'تجاوز بـ '.$this->riyals($spent - $budget).' ر.س',
                    ];
                }
            }
        }

        if ($ctx['available'] < 0) {
            $warnings[] = [
                'severity' => 'danger',
                'title' => 'المتبقي لك صار بالسالب',
                'detail' => $this->riyals($ctx['available']).' ر.س — تصرف من فلوس محجوزة لالتزاماتك.',
            ];
        }

        return $warnings;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ٢ · الميزانية — تُمنع إذا تجاوز المجموع المتاح
    // ═══════════════════════════════════════════════════════════════════

    /** @throws ValidationException */
    public function assertBudgetFits(int $newAmount, int $previousAmount, ?string $month = null): void
    {
        if ($newAmount < 0) {
            $this->fail('amount', 'الميزانية ما تكون سالبة.');
        }

        $ctx = $this->context($month);
        $totalAfter = $ctx['budgetedTotal'] - $previousAmount + $newAmount;
        $over = $totalAfter - $ctx['capacity'];

        if ($over > 0) {
            $this->fail('amount', sprintf(
                'تجاوزت المتاح بـ %s ر.س. دخلك %s ناقص التزاماتك %s = %s متاح للتوزيع.',
                $this->riyals($over),
                $this->riyals($ctx['monthlyIncome']),
                $this->riyals($ctx['obligations']),
                $this->riyals($ctx['capacity']),
            ));
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ٣ · هدف الادخار — يُمنع إذا فاق المطلوب الشهري المتاح
    // ═══════════════════════════════════════════════════════════════════

    /** @throws ValidationException */
    public function assertSavingsGoalFits(
        int $targetAmount,
        int $currentAmount,
        string $targetDate,
    ): void {
        if ($targetAmount <= $currentAmount) {
            $this->fail('target_amount', 'المبلغ المستهدف لازم يكون أكبر من المدّخر حالياً.');
        }

        $months = max(1, (int) now()->diffInMonths(CarbonImmutable::parse($targetDate), false));

        if ($months < 1) {
            $this->fail('target_date', 'التاريخ المستهدف لازم يكون في المستقبل.');
        }

        $needed = (int) ceil(($targetAmount - $currentAmount) / $months);
        $ctx = $this->context();

        if ($needed > $ctx['capacity']) {
            $feasible = $ctx['capacity'] > 0
                ? (int) ceil(($targetAmount - $currentAmount) / $ctx['capacity'])
                : 0;

            $hint = $feasible > 0
                ? sprintf(' جرّب مدة %d شهر بدل %d.', $feasible, $months)
                : '';

            $this->fail('target_amount', sprintf(
                'تحتاج تدّخر %s ر.س شهرياً، والمتاح %s ر.س فقط.%s',
                $this->riyals($needed),
                $this->riyals($ctx['capacity']),
                $hint,
            ));
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ٤ · الالتزامات (فاتورة ثابتة / قسط) — تُمنع إذا فاقت الدخل
    // ═══════════════════════════════════════════════════════════════════

    /** @throws ValidationException */
    public function assertCommitmentFits(
        int $monthlyAmount,
        int $previousAmount = 0,
        string $field = 'amount',
    ): void {
        if ($monthlyAmount <= 0) {
            $this->fail($field, 'المبلغ الشهري لازم يكون أكبر من صفر.');
        }

        $ctx = $this->context();
        $after = $ctx['obligations'] - $previousAmount + $monthlyAmount;

        if ($ctx['monthlyIncome'] > 0 && $after > $ctx['monthlyIncome']) {
            $this->fail($field, sprintf(
                'التزاماتك بتصير %s ر.س ودخلك %s ر.س. ما فيه مجال لهذا الالتزام.',
                $this->riyals($after),
                $this->riyals($ctx['monthlyIncome']),
            ));
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  أدوات
    // ═══════════════════════════════════════════════════════════════════

    /** @throws ValidationException */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function riyals(int $halalas): string
    {
        return number_format($halalas / 100, $halalas % 100 === 0 ? 0 : 2);
    }
}
