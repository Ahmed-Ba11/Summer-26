<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringTransactionRequest;
use App\Http\Requests\UpdateRecurringTransactionRequest;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(): Response
    {
        $transactions = auth()->user()->recurringTransactions()->with('category')->latest()->get();

        return Inertia::render('Recurring', [
            'transactions' => $transactions->map(fn (RecurringTransaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'category' => $transaction->category?->name,
                'source' => $transaction->source,
                'amount' => (int) $transaction->amount,
                'frequency' => $transaction->frequency,
                'next_due_date' => $transaction->next_due_date->format('Y-m-d'),
                'is_active' => (bool) $transaction->is_active,
            ])->values(),
        ]);
    }

    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $this->save($request->user()->recurringTransactions()->make(), $request->validated());

        return redirect()->back();
    }

    public function update(
        UpdateRecurringTransactionRequest $request,
        RecurringTransaction $recurringTransaction,
        RecurringTransactionService $recurring,
    ): RedirectResponse {
        abort_unless($recurringTransaction->user_id === $request->user()->id, 403);
        $this->save($recurringTransaction, $request->validated());

        if (! $recurringTransaction->is_active) {
            $recurring->detachOccurrences($recurringTransaction);
        }

        return redirect()->back();
    }

    public function destroy(
        RecurringTransaction $recurringTransaction,
        RecurringTransactionService $recurring,
    ): RedirectResponse {
        abort_unless($recurringTransaction->user_id === auth()->id(), 403);

        DB::transaction(function () use ($recurringTransaction, $recurring): void {
            $recurring->detachOccurrences($recurringTransaction);
            $recurringTransaction->delete();
        });

        return redirect()->back();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function save(RecurringTransaction $transaction, array $validated): void
    {
        $transaction->fill([
            'type' => $validated['type'],
            'category_id' => $validated['category_id'] ?? null,
            'source' => $validated['source'] ?? null,
            'amount' => Money::toHalalas($validated['amount']),
            'description' => $validated['description'] ?? null,
            'frequency' => $validated['frequency'],
            'next_due_date' => $validated['next_due_date'],
            'is_active' => $validated['is_active'] ?? true,
        ])->save();
    }
}
