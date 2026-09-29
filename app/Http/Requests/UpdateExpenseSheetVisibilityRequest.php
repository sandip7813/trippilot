<?php

namespace App\Http\Requests;

use App\Enums\ExpenseSheetVisibility;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseSheetVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return $this->user() !== null && (int) $trip->user_id === $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expense_sheet_visibility' => ['required', Rule::enum(ExpenseSheetVisibility::class)],
        ];
    }
}
