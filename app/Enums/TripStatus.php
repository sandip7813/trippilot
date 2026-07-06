<?php

namespace App\Enums;

enum TripStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Planned',
            self::Archived => 'Archived',
        };
    }
}
