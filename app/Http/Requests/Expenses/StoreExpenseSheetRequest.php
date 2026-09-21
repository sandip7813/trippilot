<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseSheetRequest extends FormRequest
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
            'participants' => ['required', 'array', 'min:1', 'max:30'],
            'participants.*.name' => ['required', 'string', 'max:100'],
            'participants.*.email' => ['required', 'email:rfc', 'max:190', 'distinct:ignore_case'],
            'participants.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
