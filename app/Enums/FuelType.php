<?php

namespace App\Enums;

enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Cng = 'cng';
    case Ev = 'ev';
    case Hybrid = 'hybrid';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Petrol',
            self::Diesel => 'Diesel',
            self::Cng => 'CNG',
            self::Ev => 'Electric (EV)',
            self::Hybrid => 'Hybrid',
            self::None => 'Not applicable',
        };
    }

    /**
     * @return list<string>
     */
    public function defaultAmenityCategories(): array
    {
        return match ($this) {
            self::Ev => ['service.vehicle.charging_station', 'service.vehicle.fuel'],
            self::Hybrid => ['service.vehicle.fuel', 'service.vehicle.charging_station'],
            self::Cng => ['service.vehicle.fuel', 'service.vehicle.charging_station'],
            self::None => [],
            default => ['service.vehicle.fuel'],
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
