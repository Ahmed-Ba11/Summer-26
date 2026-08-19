<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingBudgetRequest extends FormRequest
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
        $categoryRule = Rule::exists(Category::class, 'id')
            ->where(fn ($query) => $query->where('user_id', $this->user()->id));

        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'category_id' => ['nullable', $categoryRule, 'required_with:amount'],
            'amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'required_with:category_id'],
            'alert_percentage' => ['nullable', 'integer', 'between:1,100'],
            'budgets' => ['nullable', 'array'],
            'budgets.*.category_id' => ['required', $categoryRule],
            'budgets.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'budgets.*.alert_percentage' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
