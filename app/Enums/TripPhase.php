<?php

namespace App\Enums;

enum TripPhase: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming trips',
            self::Ongoing => 'Ongoing trips',
            self::Past => 'Past trips',
        };
    }

    /**
     * The phase of a single trip, e.g. in a status column.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Ongoing => 'Ongoing',
            self::Past => 'Completed',
        };
    }
}
