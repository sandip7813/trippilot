<?php

namespace App\Services\Hotels;

use App\Contracts\Hotels\HotelsService;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;
use Illuminate\Support\Facades\Cache;

class TripHotelsService
{
    public function __construct(
        private HotelsService $hotels,
        private TripRouteResolver $routeResolver,
    ) {}

    /**
     * Every place the trip stays in, with the first page of hotels for the first
     * place. Other places are loaded on demand through pageForLocation().
     *
     * Returns null when there is nothing to show (no Geoapify key, or no stay
     * location with saved coordinates).
     *
     * @return array{locations: list<array<string, mixed>>}|null
     */
    public function forTrip(Trip $trip): ?array
    {
        $locations = $this->locations($trip);

        if ($locations === []) {
            return null;
        }

        return [
            'locations' => array_map(
                fn (array $location, int $index): array => [
                    'label' => $location['label'],
                    ...($index === 0 ? $this->page($location, 0) : $this->unloadedPage()),
                ],
                $locations,
                array_keys($locations),
            ),
        ];
    }

    /**
     * @return array{available: bool, hotels: list<array<string, mixed>>, has_more: bool, next_offset: int, message?: string}|null
     */
    public function pageForLocation(Trip $trip, int $locationIndex, int $offset): ?array
    {
        $location = $this->locations($trip)[$locationIndex] ?? null;

        return $location === null ? null : $this->page($location, $offset);
    }

    /**
     * @return list<array{label: string, lat: float, lng: float}>
     */
    private function locations(Trip $trip): array
    {
        if (! filled(config('integrations.maps.drivers.geoapify.api_key'))) {
            return [];
        }

        $candidates = [];

        if (! $trip->isRoadTrip()) {
            foreach ($this->routeResolver->normalizedWaypoints($trip) as $waypoint) {
                $candidates[] = $waypoint['location'];
            }
        }

        $candidates[] = Trip::normalizeLocation($trip->getAttribute('destination'));

        $locations = [];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate['lat'] === null || $candidate['lng'] === null) {
                continue;
            }

            $locations[$this->coordinateKey($candidate['lat'], $candidate['lng'])] ??= [
                'label' => (string) $candidate['label'],
                'lat' => $candidate['lat'],
                'lng' => $candidate['lng'],
            ];
        }

        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));

        if ($origin !== null && $origin['lat'] !== null && $origin['lng'] !== null && count($locations) > 1) {
            unset($locations[$this->coordinateKey($origin['lat'], $origin['lng'])]);
        }

        return array_values($locations);
    }

    /**
     * @param  array{label: string, lat: float, lng: float}  $location
     * @return array{available: bool, hotels: list<array<string, mixed>>, has_more: bool, next_offset: int, message?: string}
     */
    private function page(array $location, int $offset): array
    {
        $pageSize = (int) config('integrations.hotels.page_size');
        $cacheKey = sprintf('hotels:geoapify:v4:%s:%d', $this->coordinateKey($location['lat'], $location['lng']), $offset);

        /** @var array{hotels: list<array<string, mixed>>, has_more: bool}|null $page */
        $page = Cache::get($cacheKey);

        if ($page === null) {
            $result = $this->hotels->searchNearby(
                $location['lat'],
                $location['lng'],
                $pageSize,
                $offset,
                (int) config('integrations.hotels.radius_meters'),
            );

            if ($result === null) {
                return [
                    'available' => false,
                    'hotels' => [],
                    'has_more' => false,
                    'next_offset' => $offset,
                    'message' => 'Hotels are temporarily unavailable. Please try again later.',
                ];
            }

            $page = [
                'hotels' => array_map(fn ($hotel): array => $hotel->toArray(), $result['hotels']),
                'has_more' => $result['has_more'],
            ];

            Cache::put($cacheKey, $page, (int) config('integrations.hotels.cache_ttl'));
        }

        return [
            'available' => true,
            'hotels' => $page['hotels'],
            'has_more' => $page['has_more'],
            'next_offset' => $offset + $pageSize,
        ];
    }

    /**
     * @return array{available: bool, hotels: null, has_more: bool, next_offset: int}
     */
    private function unloadedPage(): array
    {
        return ['available' => true, 'hotels' => null, 'has_more' => false, 'next_offset' => 0];
    }

    private function coordinateKey(float $latitude, float $longitude): string
    {
        return number_format($latitude, 2, '.', '').','.number_format($longitude, 2, '.', '');
    }
}
