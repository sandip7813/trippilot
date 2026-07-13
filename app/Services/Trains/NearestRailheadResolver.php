<?php

namespace App\Services\Trains;

use Illuminate\Support\Str;

class NearestRailheadResolver
{
    /**
     * @param  array<string, mixed>|null  $location
     * @return array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }|null
     */
    public function forLocation(?array $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $label = trim((string) ($location['label'] ?? ''));

        if ($label === '') {
            return null;
        }

        $placeKey = $this->primaryPlaceName($label);
        $railheads = config('integrations.trains.nearest_railheads', []);

        if (! is_array($railheads) || ! isset($railheads[$placeKey]) || ! is_array($railheads[$placeKey])) {
            return null;
        }

        /** @var array<string, mixed> $entry */
        $entry = $railheads[$placeKey];
        $code = strtoupper(trim((string) ($entry['code'] ?? '')));
        $name = trim((string) ($entry['name'] ?? ''));
        $lastMile = trim((string) ($entry['last_mile'] ?? ''));

        if ($code === '' || $name === '') {
            return null;
        }

        return [
            'place_key' => $placeKey,
            'place_label' => $label,
            'station' => [
                'code' => $code,
                'name' => $name,
            ],
            'last_mile' => $lastMile,
        ];
    }

    private function primaryPlaceName(string $label): string
    {
        $firstSegment = trim(explode(',', $label)[0] ?? $label);

        return Str::of($firstSegment)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();
    }
}
