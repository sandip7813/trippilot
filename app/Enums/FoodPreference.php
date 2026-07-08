<?php

namespace App\Enums;

enum FoodPreference: string
{
    case Any = 'any';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any',
            self::Vegetarian => 'Vegetarian',
            self::Vegan => 'Vegan',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
