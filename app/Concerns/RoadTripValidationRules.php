<?php

namespace App\Concerns;

use App\Enums\DrivingPace;
use App\Enums\FoodPreference;
use App\Enums\FuelType;
use App\Enums\VehicleClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RoadTripValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function roadProfileRules(): array
    {
        return [
            'road_profile' => ['required', 'array'],
            'road_profile.vehicle_class' => ['required', Rule::enum(VehicleClass::class)],
            'road_profile.fuel_type' => ['required', Rule::enum(FuelType::class)],
            'road_profile.driving_pace' => ['nullable', Rule::enum(DrivingPace::class)],
            'road_profile.food_preference' => ['nullable', Rule::enum(FoodPreference::class)],
            'road_profile.avoid_tolls' => ['sometimes', 'boolean'],
            'road_profile.avoid_highways' => ['sometimes', 'boolean'],
            'road_profile.ev_range_km' => ['nullable', 'integer', 'min:50', 'max:800'],
            'road_profile.max_drive_hours_per_day' => ['nullable', 'numeric', 'min:4', 'max:12'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function roadStopRules(): array
    {
        return [
            'stops' => ['nullable', 'array', 'max:20'],
            'stops.*.label' => ['required', 'string', 'max:255'],
            'stops.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'stops.*.place_id' => ['nullable', 'string', 'max:255'],
            'stops.*.kind' => ['nullable', 'string', 'in:stop,overnight,break,scenic'],
            'stops.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
