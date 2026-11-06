<?php

namespace App\Services\Airports;

use App\Data\Airports\Airport;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\Log;

/**
 * Nearest-airport lookups against the bundled, trimmed OurAirports dataset
 * (airports with scheduled passenger service and an IATA code). The file is
 * read once per process and searched in memory, so no API calls are made.
 */
class AirportDirectory
{
    public const array CSV_HEADER = ['iata_code', 'name', 'municipality', 'country_code', 'type', 'latitude', 'longitude'];

    /**
     * Airports within the configured radius, nearest first. Airports in the
     * place's own country are preferred, since a closer airport across a
     * border (e.g. Skardu for Leh) is rarely a practical way in. When nothing
     * is in range, the single nearest airport is returned anyway so remote
     * places still show how far the closest flight connection is.
     *
     * @return list<array{airport: Airport, distance_km: float}>
     */
    public function nearest(
        float $latitude,
        float $longitude,
        ?string $countryCode = null,
        ?int $limit = null,
        ?int $maxDistanceKm = null,
    ): array {
        $limit ??= (int) config('integrations.airports.max_results');
        $maxDistanceKm ??= (int) config('integrations.airports.max_distance_km');
        $countryCode = filled($countryCode) ? strtolower($countryCode) : null;

        $matches = [];

        foreach ($this->airports() as $airport) {
            $matches[] = [
                'airport' => $airport,
                'distance_km' => GeoDistance::kilometers($latitude, $longitude, $airport->latitude, $airport->longitude),
            ];
        }

        if ($matches === []) {
            return [];
        }

        usort($matches, fn (array $left, array $right): int => $left['distance_km'] <=> $right['distance_km']);

        $inRange = array_values(array_filter(
            $matches,
            fn (array $match): bool => $match['distance_km'] <= $maxDistanceKm,
        ));

        $domestic = array_values(array_filter(
            $inRange,
            fn (array $match): bool => $match['airport']->countryCode === $countryCode,
        ));

        $candidates = $domestic !== [] ? $domestic : $inRange;
        $results = $candidates !== [] ? array_slice($candidates, 0, max(1, $limit)) : [$matches[0]];

        return array_map(fn (array $match): array => [
            'airport' => $match['airport'],
            'distance_km' => round($match['distance_km'], 1),
        ], $results);
    }

    /**
     * @return list<Airport>
     */
    private function airports(): array
    {
        return once(fn (): array => $this->load());
    }

    /**
     * @return list<Airport>
     */
    private function load(): array
    {
        $path = (string) config('integrations.airports.data_path');
        $handle = is_file($path) ? fopen($path, 'r') : false;

        if ($handle === false) {
            Log::warning('Airport data file is missing. Run `php artisan trippilot:refresh-airports`.', ['path' => $path]);

            return [];
        }

        $airports = [];
        $header = fgetcsv($handle, escape: '');

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if ($header === false || count($row) !== count($header)) {
                continue;
            }

            /** @var array<string, string> $record */
            $record = array_combine($header, $row);

            if (! is_numeric($record['latitude'] ?? null) || ! is_numeric($record['longitude'] ?? null)) {
                continue;
            }

            $airports[] = new Airport(
                iataCode: (string) $record['iata_code'],
                name: (string) $record['name'],
                municipality: filled($record['municipality'] ?? null) ? (string) $record['municipality'] : null,
                countryCode: strtolower((string) $record['country_code']),
                type: (string) $record['type'],
                latitude: (float) $record['latitude'],
                longitude: (float) $record['longitude'],
            );
        }

        fclose($handle);

        return $airports;
    }
}
