<?php

namespace App\Concerns;

use App\Enums\TravelStyle;
use App\Enums\TripRouteMode;
use App\Enums\TripType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait TripValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function locationRules(string $prefix, bool $labelRequired = false): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.label" => [$labelRequired ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.lat" => ['nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.lng" => ['nullable', 'numeric', 'between:-180,180'],
            "{$prefix}.place_id" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }

    /**
     * Origin and destination must come from location search (coordinates required).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function requiredMappedLocationRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array'],
            "{$prefix}.label" => ['required', 'string', 'max:255'],
            "{$prefix}.lat" => ['required', 'numeric', 'between:-90,90'],
            "{$prefix}.lng" => ['required', 'numeric', 'between:-180,180'],
            "{$prefix}.place_id" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }

    protected function optionalMappedLocationRules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.label" => ['nullable', 'string', 'max:255'],
            "{$prefix}.lat" => ['nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.lng" => ['nullable', 'numeric', 'between:-180,180'],
            "{$prefix}.place_id" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => ['nullable', 'string', 'size:2', 'alpha'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function tripRules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::enum(TripType::class)],
            'travel_style' => ['nullable', Rule::enum(TravelStyle::class)],
            'start_date' => [
                'nullable',
                'date',
                ...($updating ? [] : ['after_or_equal:today']),
            ],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'travelers' => [$required, 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->requiredMappedLocationRules('origin'),
            ...$this->optionalMappedLocationRules('destination'),
            'route_mode' => ['nullable', Rule::enum(TripRouteMode::class)],
            'returns_to_origin' => ['nullable', 'boolean'],
            'waypoints' => ['nullable', 'array', 'max:8'],
            'waypoints.*.sequence' => ['nullable', 'integer', 'min:1'],
            'waypoints.*.nights' => ['nullable', 'integer', 'min:0', 'max:30'],
            'waypoints.*.notes' => ['nullable', 'string', 'max:1000'],
            ...$this->requiredMappedLocationRules('waypoints.*.location'),
        ];
    }

    protected function validateMultiCityTrip(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $waypoints = $this->input('waypoints');

            if (! is_array($waypoints)) {
                $waypoints = [];
            }

            $routeMode = TripRouteMode::tryFrom((string) $this->input('route_mode', ''))
                ?? (count($waypoints) >= 2 ? TripRouteMode::MultiCity : TripRouteMode::Simple);

            if ($routeMode === TripRouteMode::MultiCity) {
                if (count($waypoints) < 2) {
                    $validator->errors()->add('waypoints', 'Add at least two cities for a multi-city trip.');
                }

                return;
            }

            $destination = $this->input('destination');

            if (! is_array($destination) || ! filled($destination['label'] ?? null)) {
                $validator->errors()->add('destination', 'Pick a destination from the search suggestions.');
            }

            if (! is_array($destination) || ! isset($destination['lat'], $destination['lng'])) {
                $validator->errors()->add('destination', 'Pick a destination from the search suggestions.');
            }
        });
    }
}
