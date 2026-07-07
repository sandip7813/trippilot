<?php

namespace App\Enums;

enum TripScope: string
{
    case Domestic = 'domestic';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::Domestic => 'Domestic',
            self::International => 'International',
        };
    }
}
