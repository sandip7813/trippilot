<?php

namespace App\Enums;

enum TravelStyle: string
{
    case Family = 'family';
    case Business = 'business';
    case Adventure = 'adventure';
    case Romantic = 'romantic';
    case Backpacking = 'backpacking';
    case Solo = 'solo';
    case Group = 'group';
    case Pilgrimage = 'pilgrimage';
    case Cruise = 'cruise';
    case Weekend = 'weekend';

    public function label(): string
    {
        return match ($this) {
            self::Family => 'Family',
            self::Business => 'Business',
            self::Adventure => 'Adventure',
            self::Romantic => 'Romantic',
            self::Backpacking => 'Backpacking',
            self::Solo => 'Solo',
            self::Group => 'Group',
            self::Pilgrimage => 'Pilgrimage',
            self::Cruise => 'Cruise',
            self::Weekend => 'Weekend getaway',
        };
    }
}
