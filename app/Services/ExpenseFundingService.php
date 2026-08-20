<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تمويل المصروف — «من وين جاء المبلغ؟».
 *
 * ══════════════════════════════════════════════════════════════════════
 *  الفلوس لازم تجي من مكان.
 * ══════════════════════════════════════════════════════════════════════
 *
 * لما يتجاوز المصروف المتبقي للصرف، لا نمنعه (فهو واقعة حصلت) ولا نحفظه
 * صامتاً (فيصير رقماً سالباً بلا مصدر). نطلب مصدراً صريحاً:
 *
 *   savings         → يُخصم من هدف ادخار فعلياً
 *   unlogged_income → يُنشأ سجل دخل بالمبلغ الناقص
 *   overspend       → يُحفظ بعلامة تجاوز (الملاذ الأخير)
 *
 * كل المبالغ بالهللات (integer).
 */
final class ExpenseFundingService
{
    public const SOURCES = ['savings', 'unlogged_income', 'overspend'];

    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    /** كم يتجاوز هذا المصروف المتبقي للصرف؟ صفر يعني لا يتجاوز. */
    public function shortfall(int $amount): int
    {
        $available = BudgetGuard::for($this->user)->context()['available'];

        return max(0, $amount - $available);
    }

    /**
     * يسجّل المصروف مع مصدر تمويله في معاملة واحدة.
     *
     * @param  array{
     *   amount:int, category_id:?int, description:?string, expense_date:string,
     *   funding_source:?string, savings_goal_id:?int,
     *   income_amount:?int, income_source:?string
     * }  $data
     *
     * @throws ValidationException
     */
    public function record(array $data): Expense
    {
        $amount = (int) $data['amount'];
        $shortfall = $this->shortfall($amount);

        // لا تجاوز — مسار عادي، لا نطلب مصدراً
        if ($shortfall === 0) {
            return $this->createExpense($data);
        }

        $source = $data['funding_source'] ?? null;

        if (! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages([
                'funding_source' => 'هذا المبلغ يتجاوز المتبقي لك — لازم تحدّد من وين جاء.',
            ]);
        }

        return DB::transaction(function () use ($data, $source, $shortfall): Expense {
            match ($source) {
                'savings' => $this->drawFromSavings($data['savings_goal_id'] ?? null, $shortfall),
                'unlogged_income' => $this->logMissingIncome(
                    (int) ($data['income_amount'] ?? 0),
                    $data['income_source'] ?? null,
                    $shortfall,
                    $data['expense_date'],
                ),
                'overspend' => null,
            };

            return $this->createExpense($data, $source);
        });
    }

    // ═══════════════════════════════════════════════════════════════════

    /** @throws ValidationException */
    private function drawFromSavings(?int $goalId, int $shortfall): void
    {
        if ($goalId === null) {
            throw ValidationException::withMessages([
                'savings_goal_id' => 'اختر الهدف اللي بتسحب منه.',
            ]);
        }

        /** @var SavingsGoal|null $goal */
        $goal = $this->user->savingsGoals()->lockForUpdate()->find($goalId);

        if ($goal === null) {
            throw ValidationException::withMessages([
                'savings_goal_id' => 'الهدف غير موجود.',
            ]);
        }

        if ((int) $goal->current_amount < $shortfall) {
            throw ValidationException::withMessages([
                'savings_goal_id' => sprintf(
                    'رصيد «%s» %s ر.س فقط، والمطلوب %s ر.س.',
                    $goal->name,
                    number_format($goal->current_amount / 100, 2),
                    number_format($shortfall / 100, 2),
                ),
            ]);
        }

        $goal->decrement('current_amount', $shortfall);

        // السحب يلغي اكتمال الهدف إن كان مكتملاً
        if ($goal->is_completed && (int) $goal->fresh()->current_amount < (int) $goal->target_amount) {
            $goal->update(['is_completed' => false]);
        }
    }

    /** @throws ValidationException */
    private function logMissingIncome(
        int $incomeAmount,
        ?string $incomeSource,
        int $shortfall,
        string $date,
    ): void {
        if ($incomeAmount < $shortfall) {
            throw ValidationException::withMessages([
                'income_amount' => sprintf(
                    'المبلغ لازم يغطّي العجز على الأقل (%s ر.س).',
                    number_format($shortfall / 100, 2),
                ),
            ]);
        }

        Income::create([
            'user_id' => $this->user->id,
            'amount' => $incomeAmount,
            'source' => $incomeSource ?: 'دخل غير مسجّل',
            'description' => 'سُجّل عند تغطية مصروف',
            'income_date' => $date,
            'is_recurring' => false,
        ]);
    }

    private function createExpense(array $data, ?string $fundingSource = null): Expense
    {
        return Expense::create([
            'user_id' => $this->user->id,
            'category_id' => $data['category_id'] ?? null,
            'amount' => (int) $data['amount'],
            'description' => $data['description'] ?? null,
            'expense_date' => $data['expense_date'],
            'is_recurring' => false,
            'funding_source' => $fundingSource,
        ]);
    }

    /**
     * أهداف الادخار الصالحة للسحب منها، مع تقدير التأخير لكل ريال.
     *
     * @return list<array{
     *   id:int, name:string, icon:string, color:string,
     *   current:int, target:int, monthsBehindPerRiyal:float
     * }>
     */
    public function fundableGoals(): array
    {
        return $this->user->savingsGoals()
            ->where('current_amount', '>', 0)
            ->get()
            ->map(function (SavingsGoal $g): array {
                $months = $g->target_date
                    ? max(1, (int) now()->diffInMonths($g->target_date, false))
                    : 12;
                $remaining = max(1, (int) $g->target_amount - (int) $g->current_amount);
                $perMonth = $remaining / $months;

                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'icon' => $g->icon ?: 'vault',
                    'color' => '#1baf7a',
                    'current' => (int) $g->current_amount,
                    'target' => (int) $g->target_amount,
                    // كم شهراً يتأخر الهدف مقابل كل ريال يُسحب
                    'monthsBehindPerRiyal' => $perMonth > 0 ? 1 / $perMonth : 0.0,
                ];
            })
            ->values()
            ->all();
    }
}
