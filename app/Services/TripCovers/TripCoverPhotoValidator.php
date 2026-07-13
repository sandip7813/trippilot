<?php

namespace App\Services\TripCovers;

class TripCoverPhotoValidator
{
    /** @var list<string> */
    private const PORTRAIT_KEYWORDS = [
        'portrait',
        'headshot',
        'selfie',
        'close-up face',
        'fashion model',
        'studio portrait',
    ];

    /** @var list<string> */
    private const TRAVEL_KEYWORDS = [
        'landmark',
        'monument',
        'temple',
        'architecture',
        'landscape',
        'cityscape',
        'skyline',
        'beach',
        'mountain',
        'fort',
        'palace',
        'mosque',
        'cathedral',
        'street',
        'countryside',
        'aerial',
        'sunrise',
        'sunset',
        'campus',
        'university',
        'railway',
    ];

    /**
     * @param  array<string, mixed>  $photo
     * @param  array<string, mixed>  $destination
     * @param  list<string>  $terms
     */
    public function matchesDestination(array $photo, array $destination, array $terms): bool
    {
        if ($this->looksLikePortrait($photo)) {
            return false;
        }

        if ($this->mentionsExcludedPlace($photo, $destination)) {
            return false;
        }

        if ($this->photoMentionsTerms($photo, $terms)) {
            return true;
        }

        return $this->photoNearDestination($photo, $destination);
    }

    /**
     * @param  array<string, mixed>  $photo
     * @param  array<string, mixed>  $destination
     */
    private function mentionsExcludedPlace(array $photo, array $destination): bool
    {
        $countryCode = strtolower((string) ($destination['country_code'] ?? ''));
        $text = strtolower($this->photoText($photo));

        foreach ($this->excludedTermsForCountry($countryCode) as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        $photoCountry = strtolower(trim((string) data_get($photo, 'location.country', '')));

        if ($photoCountry === '' || $countryCode === '') {
            return false;
        }

        return ! $this->countriesMatch($countryCode, $photoCountry);
    }

    /**
     * @return list<string>
     */
    private function excludedTermsForCountry(string $countryCode): array
    {
        return match ($countryCode) {
            'in' => [
                'dhaka',
                'bangladesh',
                'sylhet',
                'chittagong',
                'cox bazar',
                'pakistan',
                'karachi',
                'islamabad',
                'lahore',
                'colombo',
                'sri lanka',
                'kathmandu',
                'nepal',
                'maldives',
                'thimphu',
                'bhutan',
                'shiv nadar',
                'greater noida',
                'noida',
            ],
            'bd' => [
                'india',
                'kolkata',
                'delhi',
                'mumbai',
                'chennai',
                'pakistan',
                'nepal',
            ],
            'np' => ['india', 'delhi', 'china', 'tibet', 'bangladesh'],
            'lk' => ['india', 'bangladesh', 'maldives'],
            default => [],
        };
    }

    private function countriesMatch(string $countryCode, string $photoCountry): bool
    {
        $normalizedPhotoCountry = match ($photoCountry) {
            'india' => 'in',
            'bangladesh' => 'bd',
            'nepal' => 'np',
            'sri lanka' => 'lk',
            'pakistan' => 'pk',
            'united states', 'usa' => 'us',
            'united kingdom', 'uk' => 'gb',
            'france' => 'fr',
            'germany' => 'de',
            'italy' => 'it',
            'spain' => 'es',
            'japan' => 'jp',
            'australia' => 'au',
            'canada' => 'ca',
            'thailand' => 'th',
            'singapore' => 'sg',
            'united arab emirates', 'uae' => 'ae',
            default => strtolower($photoCountry),
        };

        return $countryCode === $normalizedPhotoCountry;
    }

    /**
     * @param  array<string, mixed>  $photo
     * @param  list<string>  $terms
     */
    private function photoMentionsTerms(array $photo, array $terms): bool
    {
        if ($terms === []) {
            return false;
        }

        $text = strtolower($this->photoText($photo));

        foreach ($terms as $term) {
            if (str_contains($text, strtolower($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    private function looksLikePortrait(array $photo): bool
    {
        $text = strtolower($this->photoText($photo));

        foreach (self::PORTRAIT_KEYWORDS as $keyword) {
            if (! str_contains($text, $keyword)) {
                continue;
            }

            foreach (self::TRAVEL_KEYWORDS as $travelKeyword) {
                if (str_contains($text, $travelKeyword)) {
                    return false;
                }
            }

            return true;
        }

        if (preg_match('/\b(person|people|man|woman|boy|girl|model)\b/', $text) === 1) {
            foreach (self::TRAVEL_KEYWORDS as $travelKeyword) {
                if (str_contains($text, $travelKeyword)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $photo
     * @param  array<string, mixed>  $destination
     */
    private function photoNearDestination(array $photo, array $destination): bool
    {
        $lat = $destination['lat'] ?? null;
        $lng = $destination['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }

        $position = data_get($photo, 'location.position');

        if (! is_array($position)) {
            return false;
        }

        $photoLat = $position['latitude'] ?? null;
        $photoLng = $position['longitude'] ?? null;

        if (! is_numeric($photoLat) || ! is_numeric($photoLng)) {
            return false;
        }

        return $this->distanceKm((float) $lat, (float) $lng, (float) $photoLat, (float) $photoLng) <= 120;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    private function photoText(array $photo): string
    {
        $tags = $photo['tags'] ?? [];
        $tagTitles = is_array($tags)
            ? implode(' ', array_map(
                static fn (mixed $tag): string => is_array($tag) ? (string) ($tag['title'] ?? '') : '',
                $tags,
            ))
            : '';

        return trim(implode(' ', array_filter([
            (string) ($photo['description'] ?? ''),
            (string) ($photo['alt_description'] ?? ''),
            (string) data_get($photo, 'location.name', ''),
            (string) data_get($photo, 'location.city', ''),
            (string) data_get($photo, 'location.country', ''),
            $tagTitles,
        ])));
    }
}
