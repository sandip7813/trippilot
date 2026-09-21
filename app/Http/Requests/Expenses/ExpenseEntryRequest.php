<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseEntryType;
use App\Enums\ExpenseSplitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExpenseEntryType::class)],
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'payers' => ['required', 'array', 'min:1', 'max:30'],
            'payers.*.participant_id' => ['required', 'string', 'max:40'],
            'payers.*.amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'split_type' => ['required', Rule::enum(ExpenseSplitType::class)],
            'split_inputs' => ['required', 'array', 'min:1', 'max:30'],
            'split_inputs.*.participant_id' => ['required', 'string', 'max:40'],
            'split_inputs.*.value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
