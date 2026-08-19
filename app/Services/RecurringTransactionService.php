<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use App\Models\RecurringTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecurringTransactionService
{
    public function processDue(int $recurringTransactionId, CarbonInterface $today): bool
    {
        return DB::transaction(function () use ($recurringTransactionId, $today): bool {
            /** @var RecurringTransaction|null $transaction */
            $transaction = RecurringTransaction::query()
                ->lockForUpdate()
                ->find($recurringTransactionId);

            if (
                $transaction === null
                || ! $transaction->is_active
                || $transaction->next_due_date->gt($today)
            ) {
                return false;
            }

            if (! in_array($transaction->type, ['expense', 'income'], true)) {
                throw new InvalidArgumentException("Unsupported recurring transaction type [{$transaction->type}].");
            }

            $this->detachMismatchedOccurrences($transaction);

            $processed = false;

            while ($transaction->next_due_date->lte($today)) {
                $processed = true;
                $dueDate = $transaction->next_due_date->toDateString();

                if ($transaction->type === 'expense') {
                    if (! $transaction->expenses()->whereDate('expense_date', $dueDate)->exists()) {
                        $transaction->expenses()->create([
                            'user_id' => $transaction->user_id,
                            'category_id' => $transaction->category_id,
                            'amount' => $transaction->amount,
                            'description' => $transaction->description,
                            'expense_date' => $dueDate,
                            'is_recurring' => true,
                        ]);
                    }
                } elseif ($transaction->type === 'income') {
                    if (! $transaction->incomes()->whereDate('income_date', $dueDate)->exists()) {
                        $transaction->incomes()->create([
                            'user_id' => $transaction->user_id,
                            'amount' => $transaction->amount,
                            'source' => $transaction->source,
                            'description' => $transaction->description,
                            'income_date' => $dueDate,
                            'is_recurring' => true,
                        ]);
                    }
                }

                $transaction->update([
                    'next_due_date' => $this->nextDueDate($transaction->next_due_date, $transaction->frequency),
                ]);
            }

            return $processed;
        }, attempts: 3);
    }

    public function createFromExpense(
        Expense $expense,
        string $frequency = 'monthly',
        ?string $nextDueDate = null,
    ): RecurringTransaction {
        $transaction = $expense->user->recurringTransactions()->create([
            'type' => 'expense',
            'category_id' => $expense->category_id,
            'source' => null,
            'amount' => (int) $expense->amount,
            'description' => $expense->description,
            'frequency' => $frequency,
            'next_due_date' => $nextDueDate ?? $expense->expense_date->format('Y-m-d'),
            'is_active' => true,
        ]);

        $expense->updateQuietly(['recurring_transaction_id' => $transaction->id]);

        return $transaction;
    }

    public function createFromIncome(
        Income $income,
        string $frequency = 'monthly',
        ?string $nextDueDate = null,
    ): RecurringTransaction {
        $transaction = $income->user->recurringTransactions()->create([
            'type' => 'income',
            'category_id' => null,
            'source' => $income->source,
            'amount' => (int) $income->amount,
            'description' => $income->description,
            'frequency' => $frequency,
            'next_due_date' => $nextDueDate ?? $income->income_date->format('Y-m-d'),
            'is_active' => true,
        ]);

        $income->updateQuietly(['recurring_transaction_id' => $transaction->id]);

        return $transaction;
    }

    public function syncExpense(
        Expense $expense,
        ?string $frequency = null,
        ?string $nextDueDate = null,
    ): void {
        $transaction = $expense->recurringTransaction;

        if (! $expense->is_recurring) {
            $expense->updateQuietly(['recurring_transaction_id' => null]);
            $this->deleteIfOrphaned($transaction);

            return;
        }

        if ($transaction === null || $transaction->type !== 'expense') {
            if ($transaction !== null) {
                $expense->updateQuietly(['recurring_transaction_id' => null]);
                $this->deleteIfOrphaned($transaction);
            }

            $this->createFromExpense($expense, $frequency ?? 'monthly', $nextDueDate);

            return;
        }

        $this->detachMismatchedOccurrences($transaction);

        $transaction->update([
            'category_id' => $expense->category_id,
            'amount' => $expense->amount,
            'description' => $expense->description,
            'frequency' => $frequency ?? $transaction->frequency,
            'next_due_date' => $nextDueDate ?? $expense->expense_date->format('Y-m-d'),
        ]);
    }

    public function syncIncome(
        Income $income,
        ?string $frequency = null,
        ?string $nextDueDate = null,
    ): void {
        $transaction = $income->recurringTransaction;

        if (! $income->is_recurring) {
            $income->updateQuietly(['recurring_transaction_id' => null]);
            $this->deleteIfOrphaned($transaction);

            return;
        }

        if ($transaction === null || $transaction->type !== 'income') {
            if ($transaction !== null) {
                $income->updateQuietly(['recurring_transaction_id' => null]);
                $this->deleteIfOrphaned($transaction);
            }

            $this->createFromIncome($income, $frequency ?? 'monthly', $nextDueDate);

            return;
        }

        $this->detachMismatchedOccurrences($transaction);

        $transaction->update([
            'source' => $income->source,
            'amount' => $income->amount,
            'description' => $income->description,
            'frequency' => $frequency ?? $transaction->frequency,
            'next_due_date' => $nextDueDate ?? $income->income_date->format('Y-m-d'),
        ]);
    }

    public function detachExpense(Expense $expense): void
    {
        $transaction = $expense->recurringTransaction;

        $expense->updateQuietly([
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);

        $this->deleteIfOrphaned($transaction);
    }

    public function detachIncome(Income $income): void
    {
        $transaction = $income->recurringTransaction;

        $income->updateQuietly([
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);

        $this->deleteIfOrphaned($transaction);
    }

    public function detachOccurrences(RecurringTransaction $transaction): void
    {
        $transaction->expenses()->withTrashed()->update([
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);
        $transaction->incomes()->withTrashed()->update([
            'is_recurring' => false,
            'recurring_transaction_id' => null,
        ]);
    }

    private function deleteIfOrphaned(?RecurringTransaction $transaction): void
    {
        if ($transaction !== null
            && ! $transaction->expenses()->withTrashed()->exists()
            && ! $transaction->incomes()->withTrashed()->exists()) {
            $transaction->delete();
        }
    }

    private function detachMismatchedOccurrences(RecurringTransaction $transaction): void
    {
        if ($transaction->type === 'expense') {
            $transaction->incomes()->withTrashed()->update([
                'is_recurring' => false,
                'recurring_transaction_id' => null,
            ]);
        }

        if ($transaction->type === 'income') {
            $transaction->expenses()->withTrashed()->update([
                'is_recurring' => false,
                'recurring_transaction_id' => null,
            ]);
        }
    }

    private function nextDueDate(CarbonInterface $dueDate, string $frequency): CarbonInterface
    {
        return match ($frequency) {
            'daily' => $dueDate->copy()->addDay(),
            'weekly' => $dueDate->copy()->addWeek(),
            'monthly' => $dueDate->copy()->addMonthNoOverflow(),
            'yearly' => $dueDate->copy()->addYearNoOverflow(),
            default => throw new InvalidArgumentException("Unsupported recurring transaction frequency [{$frequency}]."),
        };
    }
}
