<?php

namespace App\Enums;

enum VehicleClass: string
{
    case Car = 'car';
    case Motorcycle = 'motorcycle';
    case Scooter = 'scooter';
    case Camper = 'camper';
    case Bicycle = 'bicycle';

    public function label(): string
    {
        return match ($this) {
            self::Car => 'Car (4-wheeler)',
            self::Motorcycle => 'Motorcycle',
            self::Scooter => 'Scooter',
            self::Camper => 'Camper / van',
            self::Bicycle => 'Bicycle',
        };
    }

    public function routingMode(): string
    {
        return match ($this) {
            self::Car => 'drive',
            self::Motorcycle => 'motorcycle',
            self::Scooter => 'scooter',
            self::Camper => 'light_truck',
            self::Bicycle => 'bicycle',
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
