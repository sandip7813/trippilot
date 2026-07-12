<?php

namespace App\Services\Maps\Geoapify;

use App\Contracts\Maps\RoutingService;
use App\Data\Maps\RouteResult;
use App\Exceptions\RoadTripException;
use Illuminate\Support\Facades\Log;

class GeoapifyRoutingService implements RoutingService
{
    public function __construct(private GeoapifyClient $client) {}

    /**
     * @param  array<int, array{lat: float, lng: float}>  $waypoints
     * @param  list<string>  $avoid
     */
    public function getRoute(array $waypoints, string $mode = 'drive', array $avoid = []): RouteResult
    {
        if (count($waypoints) < 2) {
            throw new RoadTripException('At least two waypoints are required to calculate a route.');
        }

        if (! filled(config('integrations.maps.drivers.geoapify.api_key'))) {
            throw new RoadTripException('Geoapify API key is not configured.');
        }

        $waypointParam = collect($waypoints)
            ->map(fn (array $point): string => $point['lat'].','.$point['lng'])
            ->implode('|');

        $query = [
            'waypoints' => $waypointParam,
            'mode' => $mode,
        ];

        if ($avoid !== []) {
            $query['avoid'] = implode('|', $avoid);
        }

        $response = $this->client->get('routing', $query);

        if ($response->failed()) {
            Log::warning('Geoapify routing request failed.', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RoadTripException('Unable to calculate route. Please try again.');
        }

        /** @var list<array<string, mixed>> $features */
        $features = $response->json('features') ?? [];
        $feature = $features[0] ?? null;

        if (! is_array($feature)) {
            throw new RoadTripException('No route was returned for these waypoints.');
        }

        return $this->mapFeature($feature);
    }

    /**
     * @param  array<string, mixed>  $feature
     */
    private function mapFeature(array $feature): RouteResult
    {
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];

        $polyline = $this->extractPolyline($geometry);

        /** @var list<array<string, mixed>> $legs */
        $legs = is_array($properties['legs'] ?? null) ? $properties['legs'] : [];

        return new RouteResult(
            distanceKm: round(((float) ($properties['distance'] ?? 0)) / 1000, 1),
            durationSeconds: (int) ($properties['time'] ?? 0),
            hasTolls: (bool) ($properties['toll'] ?? false),
            polyline: $polyline,
            legs: $legs,
        );
    }

    /**
     * Geoapify returns MultiLineString geometry (one line per route leg).
     *
     * @param  array<string, mixed>  $geometry
     * @return list<array{0: float, 1: float}>
     */
    private function extractPolyline(array $geometry): array
    {
        $type = (string) ($geometry['type'] ?? 'LineString');
        /** @var list<mixed> $coordinates */
        $coordinates = is_array($geometry['coordinates'] ?? null) ? $geometry['coordinates'] : [];

        if ($coordinates === []) {
            return [];
        }

        /** @var list<list<array{0: float|int, 1: float|int}>> $lines */
        $lines = $type === 'MultiLineString'
            ? array_values(array_filter($coordinates, is_array(...)))
            : [$coordinates];

        $polyline = [];

        foreach ($lines as $line) {
            foreach ($line as $coordinate) {
                if (! is_array($coordinate) || count($coordinate) < 2) {
                    continue;
                }

                if (! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])) {
                    continue;
                }

                $polyline[] = [
                    (float) $coordinate[1],
                    (float) $coordinate[0],
                ];
            }
        }

        return $polyline;
    }
}
