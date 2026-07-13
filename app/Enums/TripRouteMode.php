<?php

namespace App\Enums;

enum TripRouteMode: string
{
    case Simple = 'simple';
    case MultiCity = 'multi_city';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Single destination',
            self::MultiCity => 'Multi-city',
        };
    }
}
