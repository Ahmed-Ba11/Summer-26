<?php

namespace App\Http\Requests;

use App\Models\RecurringTransaction;
use Illuminate\Validation\Validator;

class UpdateRecurringTransactionRequest extends StoreRecurringTransactionRequest
{
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $transaction = $this->route('recurringTransaction');

            if (! $transaction instanceof RecurringTransaction) {
                return;
            }

            $hasExpenses = $transaction->expenses()->withTrashed()->exists();
            $hasIncomes = $transaction->incomes()->withTrashed()->exists();
            $type = $this->input('type');

            if ($transaction->type !== $type && ($hasExpenses || $hasIncomes)) {
                $validator->errors()->add('type', 'لا يمكن تغيير نوع قالب مرتبط بمعاملات مسجلة.');
            }

            if (($type === 'expense' && $hasIncomes) || ($type === 'income' && $hasExpenses)) {
                $validator->errors()->add('type', 'نوع القالب لا يطابق نوع المعاملات المرتبطة به.');
            }
        }];
    }
}
