<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class EmailExpenseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1', 'max:5'],
            'recipients.*' => ['required', 'email:rfc', 'max:190', 'distinct:ignore_case'],
            'format' => ['required', 'in:csv,pdf'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
