<?php

namespace App\Http\Requests;

use App\Enums\TripStatus;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TripType::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'travelers' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
