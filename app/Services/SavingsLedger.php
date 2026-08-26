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
                'is_completed' => $balance >= (int) $goal->target_amount,
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
