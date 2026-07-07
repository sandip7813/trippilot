<?php

namespace App\Services\TripCovers;

class TripCoverPlacePhrase
{
    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function resolve(?array $destination): string
    {
        $label = trim((string) ($destination['label'] ?? ''));

        if ($label === '') {
            return 'the destination';
        }

        if (str_contains($label, ',')) {
            return $label;
        }

        $countryCode = strtolower((string) ($destination['country_code'] ?? ''));
        $countryName = $this->countryName($countryCode);

        if ($countryName !== null) {
            return "{$label}, {$countryName}";
        }

        return $label;
    }

    private function countryName(string $countryCode): ?string
    {
        if ($countryCode === '') {
            return null;
        }

        return match ($countryCode) {
            'in' => 'India',
            'us' => 'United States',
            'gb', 'uk' => 'United Kingdom',
            'fr' => 'France',
            'de' => 'Germany',
            'it' => 'Italy',
            'es' => 'Spain',
            'jp' => 'Japan',
            'au' => 'Australia',
            'ca' => 'Canada',
            'np' => 'Nepal',
            'lk' => 'Sri Lanka',
            'bd' => 'Bangladesh',
            'th' => 'Thailand',
            'sg' => 'Singapore',
            'ae' => 'United Arab Emirates',
            default => strtoupper($countryCode),
        };
    }
}
