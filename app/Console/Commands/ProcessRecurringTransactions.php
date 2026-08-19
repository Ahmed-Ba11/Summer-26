<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('recurring:process')]
#[Description('Process due recurring transactions')]
class ProcessRecurringTransactions extends Command
{
    public function __construct(private readonly RecurringTransactionService $recurringTransactionService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $processed = 0;

        RecurringTransaction::query()
            ->active()
            ->whereDate('next_due_date', '<=', $today->toDateString())
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($today, &$processed): void {
                foreach ($transactions as $transaction) {
                    if ($this->recurringTransactionService->processDue((int) $transaction->id, $today)) {
                        $processed++;
                    }
                }
            });

        $this->info("Processed {$processed} recurring transaction(s).");

        return self::SUCCESS;
    }
}
