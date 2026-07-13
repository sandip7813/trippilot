<?php

namespace App\Services\TripCovers;

class TripCoverPlacePhrase
{
    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function resolve(?array $destination): string
    {
        $phrases = $this->searchPhrases($destination);

        return $phrases[0] ?? 'the destination';
    }

    /**
     * Ordered place phrases for stock-photo search, from most specific to broader fallbacks.
     *
     * @param  array<string, mixed>|null  $destination
     * @return list<string>
     */
    public function searchPhrases(?array $destination): array
    {
        $label = trim((string) ($destination['label'] ?? ''));

        if ($label === '') {
            return [];
        }

        $countryCode = strtolower((string) ($destination['country_code'] ?? ''));
        $countryName = $this->countryName($countryCode);
        $parts = array_values(array_filter(array_map('trim', explode(',', $label))));

        if ($parts === []) {
            return [];
        }

        $phrases = [$label];

        if (! str_contains($label, ',') && $countryName !== null) {
            array_unshift($phrases, "{$parts[0]}, {$countryName}");
        } elseif ($countryName !== null && ! str_contains(strtolower($label), strtolower($countryName))) {
            $phrases[] = "{$parts[0]}, {$countryName}";
        }

        foreach ($parts as $index => $part) {
            if ($countryName !== null && strcasecmp($part, $countryName) === 0) {
                continue;
            }

            if ($countryName !== null) {
                $phrases[] = "{$part}, {$countryName}";
            } else {
                $phrases[] = $part;
            }
        }

        if (count($parts) >= 2) {
            for ($index = 1; $index < count($parts); $index++) {
                $phrases[] = implode(', ', array_slice($parts, $index));
            }
        }

        return array_values(array_unique(array_filter($phrases)));
    }

    /**
     * @param  array<string, mixed>|null  $destination
     * @return list<string>
     */
    public function wikipediaTitles(?array $destination): array
    {
        $label = strtolower(trim((string) ($destination['label'] ?? '')));

        if ($label === '') {
            return [];
        }

        return match (true) {
            str_contains($label, 'shantiniketan') => [
                'Shantiniketan',
                'Visva-Bharati University',
                'Bolpur',
            ],
            str_contains($label, 'bolpur') => [
                'Bolpur',
                'Shantiniketan',
            ],
            default => [],
        };
    }

    /**
     * Hand-tuned Unsplash queries for destinations that stock sites mislabel or under-index.
     *
     * @param  array<string, mixed>|null  $destination
     * @return list<string>
     */
    public function curatedQueries(?array $destination): array
    {
        $label = strtolower(trim((string) ($destination['label'] ?? '')));

        if ($label === '') {
            return [];
        }

        return match (true) {
            str_contains($label, 'shantiniketan') => [
                'Visva Bharati University Shantiniketan India',
                'Bolpur railway station West Bengal India',
                'Bolpur Shantiniketan West Bengal landscape',
                'Shantiniketan West Bengal India campus',
            ],
            str_contains($label, 'bolpur') => [
                'Bolpur West Bengal India railway',
                'Shantiniketan Bolpur West Bengal',
                'Bolpur town West Bengal India',
            ],
            default => [],
        };
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
