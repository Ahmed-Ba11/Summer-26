<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IncomeIndexRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
            'recurring' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:date,amount'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
