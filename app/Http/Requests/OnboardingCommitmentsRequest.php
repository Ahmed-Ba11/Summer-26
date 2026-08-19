<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingCommitmentsRequest extends FormRequest
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
            'salary_day' => ['nullable', 'integer', 'between:1,31'],
            'commitments' => ['nullable', 'array'],
            'commitments.*.type' => ['required', 'in:bill,installment'],
            'commitments.*.name' => ['required', 'string', 'max:255'],
            'commitments.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'commitments.*.monthly_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'commitments.*.total_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'commitments.*.total_months' => ['nullable', 'integer', 'min:1'],
            'commitments.*.due_date' => ['nullable', 'date'],
            'commitments.*.due_day' => ['nullable', 'integer', 'between:1,31'],
            'commitments.*.start_date' => ['nullable', 'date_format:Y-m'],
            'commitments.*.icon' => ['nullable', 'string', 'max:50'],
            'commitments.*.account_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
