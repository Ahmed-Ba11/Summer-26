<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->transactionRules();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function transactionRules(): array
    {
        $categoryRule = Rule::exists(Category::class, 'id')
            ->where(fn ($query) => $query->where('user_id', $this->user()->id));

        return [
            'type' => ['required', 'in:income,expense'],
            'category_id' => [Rule::requiredIf(fn (): bool => $this->input('type') === 'expense'), 'nullable', $categoryRule],
            'source' => [Rule::requiredIf(fn (): bool => $this->input('type') === 'income'), 'nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'next_due_date' => ['required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
