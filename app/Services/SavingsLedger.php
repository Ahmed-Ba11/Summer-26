<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SalaryPeriod;
use App\Models\SavingsDeposit;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * كل حركة على رصيد هدف ادخار تمرّ من هنا.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ممنوع increment/decrement مباشر على `savings_goals.current_amount`.
 * ══════════════════════════════════════════════════════════════════════
 *
 * بدون صفّ في `savings_deposits` يستحيل معرفة «كم أُودع هذا الشهر»،
 * فينكسر «المتبقي لك». الإيداع صفّ موجب والسحب صفّ سالب، والرصيد
 * يُحدَّث في نفس المعاملة.
 *
 * كل المبالغ بالهللات (integer).
 */
final class SavingsLedger
{
    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function deposit(SavingsGoal $goal, int $amount, ?string $date = null): SavingsDeposit
    {
        return $this->move($goal, abs($amount), $date);
    }

    public function withdraw(SavingsGoal $goal, int $amount, ?string $date = null): SavingsDeposit
    {
        return $this->move($goal, -abs($amount), $date);
    }

    /**
     * تعديل مبلغ حركة قائمة — الإيداع يبقى إيداعاً والسحب سحباً، وإنما
     * تتغيّر قيمته. الرصيد يُعاد حسابه من الحركات لا يُزاد بالفرق، فلا
     * يتراكم انحراف مع كل تصحيح.
     */
    public function amend(SavingsDeposit $entry, int $amount): SavingsDeposit
    {
        return DB::transaction(function () use ($entry, $amount): SavingsDeposit {
            $entry->update([
                'amount' => $entry->amount < 0 ? -abs($amount) : abs($amount),
            ]);

            $this->resync((int) $entry->savings_goal_id);

            return $entry->refresh();
        });
    }

    /** حذف حركة — الرصيد يعود كأنها لم تكن. */
    public function remove(SavingsDeposit $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $goalId = (int) $entry->savings_goal_id;
            $entry->delete();

            $this->resync($goalId);
        });
    }

    /**
     * الرصيد = مجموع حركات الهدف.
     *
     * `move()` تزيد الرصيد بالفرق لأنها تضيف حركة جديدة، أمّا التعديل
     * والحذف فيغيّران التاريخ نفسه — فإعادة الجمع هي الطريقة الوحيدة
     * التي تبقي `current_amount` مطابقاً لكشف الحركات.
     */
    private function resync(int $goalId): void
    {
        $goal = SavingsGoal::query()
            ->where('user_id', $this->user->id)
            ->whereKey($goalId)
            ->first();

        if ($goal === null) {
            return;
        }

        $balance = max(0, (int) $goal->deposits()->sum('amount'));

        $goal->update([
            'current_amount' => $balance,
            'is_completed' => $goal->hasReachedTarget($balance),
        ]);
    }

    /** صافي ما أُودع في فترة راتب واحدة (الإيداعات ناقص السحوبات). */
    public function netForPeriod(?string $periodKey = null): int
    {
        $periodKey ??= SalaryPeriod::keyFor($this->user);

        return (int) $this->user->savingsDeposits()
            ->where('period_key', $periodKey)
            ->sum('amount');
    }

    private function move(SavingsGoal $goal, int $delta, ?string $date): SavingsDeposit
    {
        $date ??= now()->toDateString();

        return DB::transaction(function () use ($goal, $delta, $date): SavingsDeposit {
            $balance = max(0, (int) $goal->current_amount + $delta);

            $goal->update([
                'current_amount' => $balance,
                'is_completed' => $goal->hasReachedTarget($balance),
            ]);

            return $this->user->savingsDeposits()->create([
                'savings_goal_id' => $goal->id,
                'amount' => $delta,
                'deposited_at' => $date,
                'period_key' => SalaryPeriod::keyFor($this->user, $date),
            ]);
        });
    }
}
