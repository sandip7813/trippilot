<?php

namespace App\Http\Requests;

use App\Concerns\RoadTripValidationRules;
use App\Concerns\TripValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateRoadTripRequest extends FormRequest
{
    use RoadTripValidationRules;
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
            ...Arr::except($this->tripRules(updating: true), ['type']),
            ...$this->roadProfileRules(),
            ...$this->roadStopRules(),
            'suggested_breaks' => ['sometimes', 'array'],
            'suggested_breaks.*.id' => ['required', 'string'],
            'suggested_breaks.*.label' => ['required', 'string'],
            'suggested_breaks.*.lat' => ['required', 'numeric'],
            'suggested_breaks.*.lng' => ['required', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateMultiCityTrip($validator);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'road_profile' => $this->normalizedRoadProfile(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRoadProfile(): array
    {
        $profile = $this->input('road_profile');

        if (! is_array($profile)) {
            return [];
        }

        foreach (['driving_pace', 'food_preference'] as $key) {
            if (($profile[$key] ?? null) === '') {
                $profile[$key] = null;
            }
        }

        foreach (['avoid_tolls', 'avoid_highways'] as $key) {
            if (array_key_exists($key, $profile)) {
                $profile[$key] = filter_var($profile[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $profile;
    }
}
