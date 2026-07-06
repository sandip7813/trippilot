<?php

namespace App\Concerns;

use App\Enums\TravelStyle;
use App\Enums\TripType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'travelers' => [$required, 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->locationRules('origin', $this->input('type') === TripType::Road->value),
            ...$this->locationRules('destination'),
        ];
    }
}
