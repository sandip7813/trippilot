<?php

namespace App\Enums;

enum DrivingPace: string
{
    case Relaxed = 'relaxed';
    case Standard = 'standard';
    case LongDays = 'long_days';

    public function label(): string
    {
        return match ($this) {
            self::Relaxed => 'Relaxed (more breaks)',
            self::Standard => 'Standard',
            self::LongDays => 'Long driving days',
        };
    }

    public function maxDriveHoursPerDay(): float
    {
        return match ($this) {
            self::Relaxed => 6.0,
            self::Standard => 8.0,
            self::LongDays => 10.0,
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
