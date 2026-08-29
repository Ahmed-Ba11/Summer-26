<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
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
            'month' => ['nullable', 'date_format:Y-m'],
            // المدد الصريحة: مدى متدحرج بالأيام أو فترة راتب كاملة
            'range' => ['nullable', 'in:15d,30d,60d,month'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $month = $this->input('month');

            if (is_string($month) && preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) === 1
                && ! checkdate((int) $matches[2], 1, (int) $matches[1])) {
                $validator->errors()->add('month', 'الشهر غير صالح.');
            }
        }];
    }
}
