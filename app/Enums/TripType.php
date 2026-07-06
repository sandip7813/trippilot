<?php

namespace App\Enums;

enum TripType: string
{
    case Vacation = 'vacation';
    case Road = 'road';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacation',
            self::Road => 'Road trip',
        };
    }
}
