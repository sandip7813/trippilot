<?php

namespace App\Http\Requests;

use App\Concerns\OpenTripDetailsValidationRules;
use App\Concerns\RoadTripValidationRules;
use App\Concerns\TripValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class StoreRoadTripRequest extends FormRequest
{
    use OpenTripDetailsValidationRules;
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
            ...Arr::except($this->tripRules(), ['type']),
            ...$this->roadProfileRules(),
            'make_open_trip' => ['sometimes', 'boolean'],
            'open_trip' => ['sometimes', 'array'],
            ...$this->openTripDetailsRules('open_trip.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateMultiCityTrip($validator);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Pick a start date.',
            'end_date.required' => 'Pick an end date.',
        ];
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
