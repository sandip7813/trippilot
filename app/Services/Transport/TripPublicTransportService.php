<?php

namespace App\Services\Transport;

use App\Contracts\Maps\PlacesService;
use App\Data\Maps\PlaceResult;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\Cache;

/**
 * The "Public transport" tab: per place the trip stays in, the nearest
 * metro, train, tram, ferry, cable car and bus stops with their operators
 * and routes, plus taxis and car or bike rentals, all from Geoapify and
 * OpenStreetMap. Loaded for the first stay only; other stays are fetched on
 * demand through forLocation().
 */
class TripPublicTransportService
{
    /**
     * Public transport modes in display order, with the Geoapify categories
     * that map to each. A stop tagged with several categories is listed
     * under the first mode that matches.
     *
     * @var array<string, array{label: string, categories: list<string>}>
     */
    private const array MODES = [
        'metro' => ['label' => 'Metro', 'categories' => ['public_transport.subway', 'public_transport.light_rail', 'public_transport.monorail']],
        'train' => ['label' => 'Train', 'categories' => ['public_transport.train']],
        'tram' => ['label' => 'Tram', 'categories' => ['public_transport.tram']],
        'ferry' => ['label' => 'Ferry', 'categories' => ['public_transport.ferry']],
        'cable_car' => ['label' => 'Cable car', 'categories' => ['public_transport.aerialway']],
        'bus' => ['label' => 'Bus', 'categories' => ['public_transport.bus']],
    ];

    /**
     * Taxi and rental services, in display order.
     *
     * @var array<string, array{label: string, category: string}>
     */
    private const array SERVICES = [
        'taxi' => ['label' => 'Taxi', 'category' => 'service.taxi'],
        'car_rental' => ['label' => 'Car rental', 'category' => 'rental.car'],
        'bike_rental' => ['label' => 'Bike rental', 'category' => 'rental.bicycle'],
    ];

    public function __construct(
        private PlacesService $places,
        private TripRouteResolver $routeResolver,
        private TransportPlaceDetails $details,
    ) {}

    /**
     * Returns null when there is nothing to show (no Geoapify key, or no
     * mapped place the trip stays in). A place's `stops` and `services` are
     * null until loaded.
     *
     * @return array{locations: list<array{label: string, lat: float, lng: float, stops: list<array<string, mixed>>|null, services: list<array<string, mixed>>|null}>}|null
     */
    public function forTrip(Trip $trip): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $locations = $this->routeResolver->stayLocations($trip);

        if ($locations === []) {
            return null;
        }

        return [
            'locations' => array_map(
                fn (array $location, int $index): array => [
                    'label' => $location['label'],
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'stops' => $index === 0 ? $this->stops($location) : null,
                    'services' => $index === 0 ? $this->services($location) : null,
                ],
                $locations,
                array_keys($locations),
            ),
        ];
    }

    /**
     * @return array{stops: list<array<string, mixed>>, services: list<array<string, mixed>>}|null
     */
    public function forLocation(Trip $trip, int $locationIndex): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $location = $this->routeResolver->stayLocations($trip)[$locationIndex] ?? null;

        return $location === null ? null : [
            'stops' => $this->stops($location),
            'services' => $this->services($location),
        ];
    }

    private function enabled(): bool
    {
        return filled(config('integrations.maps.drivers.geoapify.api_key'));
    }

    /**
     * Nearest stops per public transport mode. Buses are searched separately
     * so the many bus stops in a city centre don't crowd out metro and rail
     * stations from the single 20-result page Geoapify returns.
     *
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return list<array{mode: string, label: string, operators: list<array{name: string|null, network: string|null}>, stops: list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>}>
     */
    private function stops(array $location): array
    {
        $cacheKey = 'transport:stops:v3:'.$this->routeResolver->coordinateKey($location['lat'], $location['lng']);

        /** @var list<array{mode: string, label: string, operators: list<array{name: string|null, network: string|null}>, stops: list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $radius = (int) config('integrations.transport.stops_radius_meters');
        $railCategories = collect(self::MODES)->except('bus')->pluck('categories')->flatten()->all();

        $places = [
            ...$this->places->searchNearby($location['lat'], $location['lng'], implode(',', $railCategories), 20, $radius),
            ...$this->places->searchNearby($location['lat'], $location['lng'], implode(',', self::MODES['bus']['categories']), 20, $radius),
        ];

        $groups = $this->groupStops($places, $location);

        Cache::put(
            $cacheKey,
            $groups,
            (int) config($groups === [] ? 'integrations.transport.failure_cache_ttl' : 'integrations.transport.cache_ttl'),
        );

        return $groups;
    }

    /**
     * Groups stops by mode, nearest first. The same station is often mapped
     * several times (e.g. once per metro line); those are merged so details
     * such as bus route numbers from any copy are kept.
     *
     * @param  list<PlaceResult>  $places
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return list<array{mode: string, label: string, operators: list<array{name: string|null, network: string|null}>, stops: list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>}>
     */
    private function groupStops(array $places, array $location): array
    {
        $perMode = max(1, (int) config('integrations.transport.stops_per_mode'));
        $byMode = [];

        foreach ($places as $place) {
            $mode = $this->modeFor($place->category);
            $nameKey = mb_strtolower(trim($place->name));

            if ($mode === null || $nameKey === '') {
                continue;
            }

            $stop = [
                'name' => $place->name,
                'distance_meters' => $this->distanceMeters($location, $place),
                'lat' => $place->latitude,
                'lng' => $place->longitude,
                ...$this->details->stop($place),
            ];

            $byMode[$mode][$nameKey] = isset($byMode[$mode][$nameKey])
                ? $this->mergeStops($byMode[$mode][$nameKey], $stop)
                : $stop;
        }

        $groups = [];

        foreach (self::MODES as $mode => $definition) {
            if (! isset($byMode[$mode])) {
                continue;
            }

            $stops = array_values($byMode[$mode]);
            usort($stops, fn (array $left, array $right): int => $left['distance_meters'] <=> $right['distance_meters']);

            $shown = array_slice($stops, 0, $perMode);

            if ($mode === 'bus') {
                $shown = $this->withRoutedBusStop($shown, $stops);
            }

            $groups[] = [
                'mode' => $mode,
                'label' => $definition['label'],
                'operators' => $this->operators($shown),
                'stops' => $shown,
            ];
        }

        return $groups;
    }

    /**
     * @param  array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}  $stop
     * @param  array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}  $duplicate
     * @return array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}
     */
    private function mergeStops(array $stop, array $duplicate): array
    {
        $nearest = $duplicate['distance_meters'] < $stop['distance_meters'] ? $duplicate : $stop;

        return [
            ...$nearest,
            'code' => $stop['code'] ?? $duplicate['code'],
            'operator' => $stop['operator'] ?? $duplicate['operator'],
            'network' => $stop['network'] ?? $duplicate['network'],
            'routes' => array_values(array_unique([...$stop['routes'], ...$duplicate['routes']])),
            'wheelchair' => $stop['wheelchair'] ?? $duplicate['wheelchair'],
            'shelter' => $stop['shelter'] ?? $duplicate['shelter'],
        ];
    }

    /**
     * Few bus stops in OpenStreetMap list the routes that serve them. When
     * none of the nearest do, the closest one that does is added, so the
     * traveler still learns which bus numbers run nearby.
     *
     * @param  list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>  $shown
     * @param  list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>  $all
     * @return list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>
     */
    private function withRoutedBusStop(array $shown, array $all): array
    {
        foreach ($shown as $stop) {
            if ($stop['routes'] !== []) {
                return $shown;
            }
        }

        foreach ($all as $stop) {
            if ($stop['routes'] !== []) {
                return [...$shown, $stop];
            }
        }

        return $shown;
    }

    /**
     * Distinct network/operator pairs among the given stops, e.g. "Delhi
     * Metro" run by "Delhi Metro Rail Corporation Limited".
     *
     * @param  list<array{name: string, distance_meters: int, lat: float, lng: float, code: string|null, operator: string|null, network: string|null, routes: list<string>, wheelchair: string|null, shelter: bool|null}>  $stops
     * @return list<array{name: string|null, network: string|null}>
     */
    private function operators(array $stops): array
    {
        $operators = [];

        foreach ($stops as $stop) {
            if ($stop['operator'] === null && $stop['network'] === null) {
                continue;
            }

            $key = mb_strtolower(($stop['network'] ?? '').'|'.($stop['operator'] ?? ''));
            $operators[$key] ??= ['name' => $stop['operator'], 'network' => $stop['network']];
        }

        return array_values($operators);
    }

    private function modeFor(string $categories): ?string
    {
        $tags = array_map(trim(...), explode(',', $categories));

        foreach (self::MODES as $mode => $definition) {
            if (array_intersect($definition['categories'], $tags) !== []) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * Named taxi stands and car or bike rental shops with at least one way
     * to contact or visit them, nearest first.
     *
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return list<array{kind: string, kind_label: string, name: string, operator: string|null, address: string|null, phones: list<string>, email: string|null, website: string|null, opening_hours: string|null, distance_meters: int, lat: float, lng: float}>
     */
    private function services(array $location): array
    {
        $cacheKey = 'transport:services:v1:'.$this->routeResolver->coordinateKey($location['lat'], $location['lng']);

        /** @var list<array{kind: string, kind_label: string, name: string, operator: string|null, address: string|null, phones: list<string>, email: string|null, website: string|null, opening_hours: string|null, distance_meters: int, lat: float, lng: float}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $places = $this->places->searchNearby(
            $location['lat'],
            $location['lng'],
            implode(',', array_column(self::SERVICES, 'category')),
            20,
            (int) config('integrations.transport.services_radius_meters'),
        );

        $services = [];

        foreach ($places as $place) {
            $kinds = $this->serviceKindsFor($place->category);
            $details = $kinds !== [] ? $this->details->service($place) : null;

            if ($details === null) {
                continue;
            }

            $services[mb_strtolower($details['name'])] ??= [
                'kind' => $kinds[0],
                'kind_label' => $this->serviceKindLabel($kinds),
                ...$details,
                'distance_meters' => $this->distanceMeters($location, $place),
                'lat' => $place->latitude,
                'lng' => $place->longitude,
            ];
        }

        $services = array_values($services);
        usort($services, fn (array $left, array $right): int => $left['distance_meters'] <=> $right['distance_meters']);
        $services = array_slice($services, 0, max(1, (int) config('integrations.transport.services_limit')));

        Cache::put(
            $cacheKey,
            $services,
            (int) config($services === [] ? 'integrations.transport.failure_cache_ttl' : 'integrations.transport.cache_ttl'),
        );

        return $services;
    }

    /**
     * Every service kind a place is tagged with; shops often rent out both
     * cars and bikes.
     *
     * @return list<string>
     */
    private function serviceKindsFor(string $categories): array
    {
        $tags = array_map(trim(...), explode(',', $categories));

        return array_keys(array_filter(
            self::SERVICES,
            fn (array $definition): bool => in_array($definition['category'], $tags, true),
        ));
    }

    /**
     * "Taxi", "Car rental", or a combined label such as "Car & bike rental".
     *
     * @param  non-empty-list<string>  $kinds
     */
    private function serviceKindLabel(array $kinds): string
    {
        $rentals = array_values(array_intersect($kinds, ['car_rental', 'bike_rental']));

        if (count($rentals) === 2) {
            return in_array('taxi', $kinds, true) ? 'Taxi, car & bike rental' : 'Car & bike rental';
        }

        return implode(' & ', array_map(fn (string $kind): string => self::SERVICES[$kind]['label'], $kinds));
    }

    /**
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     */
    private function distanceMeters(array $location, PlaceResult $place): int
    {
        return (int) round(
            GeoDistance::kilometers($location['lat'], $location['lng'], $place->latitude, $place->longitude) * 1000,
        );
    }
}
