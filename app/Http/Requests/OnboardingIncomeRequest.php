<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingIncomeRequest extends FormRequest
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
        return [
            'amount' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2', 'required_without:incomes'],
            'source' => ['nullable', 'required_without:incomes', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
            'income_date' => ['nullable', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'frequency' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'next_due_date' => ['nullable', 'date'],
            'incomes' => ['nullable', 'array', 'min:1', 'required_without:amount'],
            'incomes.*.amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'incomes.*.source' => ['required', 'string', 'max:500'],
            'incomes.*.description' => ['nullable', 'string', 'max:500'],
            'incomes.*.income_date' => ['nullable', 'date'],
            'incomes.*.is_recurring' => ['sometimes', 'boolean'],
            'incomes.*.frequency' => ['nullable', 'in:daily,weekly,monthly,yearly'],
            'incomes.*.next_due_date' => ['nullable', 'date'],
        ];
    }
}
