<?php

namespace App\Enums;

enum TripCoverSource: string
{
    case Wikipedia = 'wikipedia';
    case CommonsGeo = 'commons_geo';
    case CommonsText = 'commons_text';
    case Unsplash = 'unsplash';
    case Pollinations = 'pollinations';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Wikipedia => 'Wikipedia',
            self::CommonsGeo => 'Wikimedia Commons (nearby)',
            self::CommonsText => 'Wikimedia Commons',
            self::Unsplash => 'Unsplash',
            self::Pollinations => 'AI generated',
            self::Upload => 'Your upload',
        };
    }

    /**
     * @return list<self>
     */
    public static function rotationLadder(): array
    {
        $ladder = [
            self::Wikipedia,
            self::CommonsGeo,
            self::CommonsText,
            self::Unsplash,
        ];

        if (filter_var(config('integrations.trip_covers.pollinations_fallback', true), FILTER_VALIDATE_BOOLEAN)) {
            $ladder[] = self::Pollinations;
        }

        return $ladder;
    }
}
