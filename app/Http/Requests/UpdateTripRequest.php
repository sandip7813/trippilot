<?php

namespace App\Http\Requests;

use App\Concerns\TripValidationRules;
use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
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
            ...$this->tripRules(updating: true),
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'is_favorite' => ['sometimes', 'boolean'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Trip $trip */
            $trip = $this->route('trip');
            $startDate = $this->input('start_date');

            if (! is_string($startDate) || $startDate === '') {
                return;
            }

            if ($trip->start_date?->toDateString() === $startDate) {
                return;
            }

            if (Carbon::parse($startDate)->startOfDay()->lt(today())) {
                $validator->errors()->add(
                    'start_date',
                    __('The start date must be today or a future date.'),
                );
            }
        });
    }
}
