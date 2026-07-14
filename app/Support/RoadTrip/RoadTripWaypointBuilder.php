<?php

namespace App\Support\RoadTrip;

use App\Enums\TripRouteMode;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;

class RoadTripWaypointBuilder
{
    public function __construct(private TripRouteResolver $routeResolver) {}

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function build(Trip $trip): array
    {
        $routePoints = $this->routeResolver->routePoints($trip);
        $origin = $routePoints[0] ?? null;

        if (
            $this->routeResolver->routeMode($trip) === TripRouteMode::MultiCity
            && $this->routeResolver->returnsToOrigin($trip)
            && is_array($origin)
        ) {
            $last = $routePoints[array_key_last($routePoints)] ?? null;

            if (is_array($last) && ! $this->locationsMatch($last, $origin)) {
                $routePoints[] = $origin;
            }
        }

        $cityPoints = collect($routePoints)
            ->map(function (?array $point): ?array {
                if ($point === null || $point['lat'] === null || $point['lng'] === null) {
                    return null;
                }

                return [
                    'lat' => (float) $point['lat'],
                    'lng' => (float) $point['lng'],
                ];
            })
            ->filter()
            ->values()
            ->all();

        /** @var list<array{lat: float, lng: float}> $cityPoints */
        $cityPoints = $this->dedupeConsecutive($cityPoints);

        if ($cityPoints === []) {
            return [];
        }

        /** @var list<array<string, mixed>> $stops */
        $stops = Trip::coerceStructuredArray($trip->getAttribute('stops')) ?? [];

        $stopPoints = [];

        foreach ($stops as $stop) {
            $lat = isset($stop['lat']) ? (float) $stop['lat'] : null;
            $lng = isset($stop['lng']) ? (float) $stop['lng'] : null;

            if ($lat === null || $lng === null) {
                continue;
            }

            $stopPoints[] = ['lat' => $lat, 'lng' => $lng];
        }

        if ($stopPoints === []) {
            return $cityPoints;
        }

        if (count($cityPoints) === 1) {
            return [...$cityPoints, ...$stopPoints];
        }

        $last = array_pop($cityPoints);

        return [...$cityPoints, ...$stopPoints, $last];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<array{lat: float, lng: float}>
     */
    private function dedupeConsecutive(array $points): array
    {
        $deduped = [];

        foreach ($points as $point) {
            $previous = $deduped !== [] ? $deduped[array_key_last($deduped)] : null;

            if ($previous !== null
                && abs($previous['lat'] - $point['lat']) < 0.000001
                && abs($previous['lng'] - $point['lng']) < 0.000001) {
                continue;
            }

            $deduped[] = $point;
        }

        return $deduped;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function locationsMatch(array $left, array $right): bool
    {
        if ($left['lat'] === null || $left['lng'] === null || $right['lat'] === null || $right['lng'] === null) {
            return false;
        }

        return abs((float) $left['lat'] - (float) $right['lat']) < 0.000001
            && abs((float) $left['lng'] - (float) $right['lng']) < 0.000001;
    }
}
