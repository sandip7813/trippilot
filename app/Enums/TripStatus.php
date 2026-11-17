<?php

namespace App\Enums;

/**
 * Whether the trip has an AI itinerary: Draft until one is generated (and
 * again after dates or destination change), Planned once it exists.
 */
enum TripStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'No itinerary yet',
            self::Planned => 'Itinerary ready',
            self::Archived => 'Archived',
        };
    }
}
