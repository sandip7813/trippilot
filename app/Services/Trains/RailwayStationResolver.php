<?php

namespace App\Services\Trains;

use App\Contracts\Maps\PlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RailwayStationResolver
{
    public function __construct(
        private RailRadarClient $client,
        private PlacesService $placesService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $location
     * @return array{code: string, name: string}|null
     */
    public function resolve(?array $location): ?array
    {
        $location = $this->normalizeLocation($location);

        if ($location === null) {
            return null;
        }

        $directory = $this->stationDirectory();

        if ($directory === []) {
            return null;
        }

        $aliasMatch = $this->matchAlias($location['label'], $directory);

        if ($aliasMatch !== null) {
            return $aliasMatch;
        }

        $labelMatch = $this->matchLabel($location['label'], $directory);

        if ($labelMatch !== null) {
            return $labelMatch;
        }

        return $this->matchNearbyPlaces($location, $directory);
    }

    /**
     * @return array<string, string>
     */
    public function stationDirectory(): array
    {
        if (! filled(config('integrations.trains.drivers.railradar.api_key'))) {
            return [];
        }

        $cacheTtl = (int) config('integrations.trains.station_lookup_cache_ttl', 604800);

        return Cache::remember('railradar:stations:lookup', $cacheTtl, function (): array {
            $response = $this->client->stationsLookup();

            if ($response->failed()) {
                Log::warning('RailRadar station lookup failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            /** @var array<string, string>|null $stations */
            $stations = $response->json('data');

            return is_array($stations) ? $stations : [];
        });
    }

    /**
     * @param  array<string, mixed>|null  $location
     * @return array{label: string, lat: float, lng: float}|null
     */
    private function normalizeLocation(?array $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $label = trim((string) ($location['label'] ?? ''));

        if ($label === '') {
            return null;
        }

        $lat = $location['lat'] ?? null;
        $lng = $location['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [
            'label' => $label,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
        ];
    }

    /**
     * @param  array<string, string>  $directory
     * @return array{code: string, name: string}|null
     */
    private function matchAlias(string $label, array $directory): ?array
    {
        $needle = $this->primaryPlaceName($label);
        $aliases = config('integrations.trains.station_aliases', []);

        if (! is_array($aliases) || ! isset($aliases[$needle])) {
            return null;
        }

        $code = strtoupper((string) $aliases[$needle]);

        if (! isset($directory[$code])) {
            return null;
        }

        return [
            'code' => $code,
            'name' => $directory[$code],
        ];
    }

    /**
     * @param  array<string, string>  $directory
     * @return array{code: string, name: string}|null
     */
    private function matchLabel(string $label, array $directory): ?array
    {
        $needle = $this->primaryPlaceName($label);

        if ($needle === '') {
            return null;
        }

        $candidates = [];

        foreach ($directory as $code => $name) {
            $normalizedName = $this->normalizeToken($name);

            if ($normalizedName === '' || ! str_contains($normalizedName, $needle)) {
                continue;
            }

            $score = 0;

            if ($normalizedName === $needle) {
                $score += 100;
            }

            if (str_contains(strtolower($name), 'junction')) {
                $score += 20;
            }

            if (str_starts_with($normalizedName, $needle.' ')) {
                $score += 10;
            }

            $score -= (int) (strlen($name) / 10);

            $candidates[] = [
                'code' => $code,
                'name' => $name,
                'score' => $score,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        $best = $candidates[0];

        return [
            'code' => $best['code'],
            'name' => $best['name'],
        ];
    }

    /**
     * @param  array{label: string, lat: float, lng: float}  $location
     * @param  array<string, string>  $directory
     * @return array{code: string, name: string}|null
     */
    private function matchNearbyPlaces(array $location, array $directory): ?array
    {
        if (! filled(config('integrations.maps.drivers.geoapify.api_key'))) {
            return null;
        }

        $places = $this->placesService->searchNearPoint(
            $location['lat'],
            $location['lng'],
            ['public_transport.train'],
            limit: 8,
            radiusMeters: 25000,
        );

        foreach ($places as $place) {
            $match = $this->matchLabel($place->name, $directory);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private function primaryPlaceName(string $label): string
    {
        $firstSegment = trim(explode(',', $label)[0] ?? $label);

        return $this->normalizeToken($firstSegment);
    }

    private function normalizeToken(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();
    }
}
