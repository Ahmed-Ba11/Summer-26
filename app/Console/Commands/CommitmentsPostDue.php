<?php

namespace App\Console\Commands;

use App\Models\Commitment;
use App\Models\User;
use App\Services\CommitmentService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CommitmentsPostDue extends Command
{
    protected $signature = 'commitments:post-due';

    protected $description = 'يسجّل الخصم التلقائي للالتزامات التي حلّ موعدها، ويُشعر بالدفع الآلي.';

    public function handle(): int
    {
        $count = 0;

        User::query()->each(function (User $user) use (&$count): void {
            $service = CommitmentService::for($user);
            $period = $service->currentPeriod();
            $today = CarbonImmutable::today();

            // شرط حاسم: التزام مربوط بيوم الراتب لا يُخصم تلقائياً إلا بعد تسجيل دخل
            // ذلك الشهر فعلاً — لو تأخّر الراتب لا نُثبّت خصماً لم يحدث.
            $incomeRecorded = $service->periodIncome($period) > 0;

            $user->commitments()
                ->active()
                ->where('payment_method', 'auto')
                ->each(function (Commitment $commitment) use ($service, $period, $today, $incomeRecorded, &$count): void {
                    if ($commitment->payments()->where('period_key', $period['key'])->exists()) {
                        return;
                    }

                    $dueDate = $service->dueDateFor($commitment, $period);

                    if (! $dueDate->isSameDay($today)) {
                        return;
                    }

                    if ($commitment->due_type === 'salary_day' && ! $incomeRecorded) {
                        return;
                    }

                    $amount = $service->expectedAmount($commitment);

                    if ($amount <= 0) {
                        return;
                    }

                    DB::transaction(function () use ($commitment, $amount, $period): void {
                        $commitment->payments()->create([
                            'amount' => $amount,
                            'paid_at' => now()->toDateString(),
                            'period_key' => $period['key'],
                            'source' => 'auto',
                        ]);

                        if ($commitment->kind === 'installment' && $commitment->months_count > 0) {
                            $paid = min($commitment->months_count, $commitment->months_paid + 1);
                            $commitment->update([
                                'months_paid' => $paid,
                                'is_active' => $paid < $commitment->months_count,
                            ]);
                        }
                    });

                    if ($commitment->notify_when !== 'none') {
                        $this->line("تنبيه {$commitment->name}: تم تسجيل الخصم التلقائي.");
                    }

                    $count++;
                });
        });

        $this->info("تم تسجيل {$count} خصم تلقائي.");

        return self::SUCCESS;
    }
}
