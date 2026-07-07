<?php

namespace App\Http\Requests;

use App\Concerns\TripValidationRules;
use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    use TripValidationRules;

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
            ...$this->tripRules(),
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origin.required' => 'Pick a starting location from the search suggestions.',
            'origin.label.required' => 'Pick a starting location from the search suggestions.',
            'origin.lat.required' => 'Pick a starting location from the search suggestions.',
            'origin.lng.required' => 'Pick a starting location from the search suggestions.',
            'destination.required' => 'Pick a destination from the search suggestions.',
            'destination.label.required' => 'Pick a destination from the search suggestions.',
            'destination.lat.required' => 'Pick a destination from the search suggestions.',
            'destination.lng.required' => 'Pick a destination from the search suggestions.',
        ];
    }
}
