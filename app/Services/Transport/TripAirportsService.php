<?php

namespace App\Services\Transport;

use App\Contracts\Maps\RoutingService;
use App\Data\Airports\Airport;
use App\Data\Maps\RouteResult;
use App\Exceptions\RoadTripException;
use App\Models\Trip;
use App\Services\Airports\AirportDirectory;
use App\Services\Trips\TripRouteResolver;
use Illuminate\Support\Facades\Cache;

/**
 * The "Airports" tab: the nearest airports to where the trip starts and to
 * each place it stays in (from bundled OurAirports data, no API calls), and
 * per stay an estimate of getting from the nearest airport into town by car
 * and by public transport (from Geoapify). Transfer estimates are loaded for
 * the first stay only; other stays are fetched on demand.
 */
class TripAirportsService
{
    public function __construct(
        private AirportDirectory $airports,
        private RoutingService $routing,
        private TripRouteResolver $routeResolver,
    ) {}

    /**
     * Returns null when there is nothing to show (no mapped stay or origin).
     * Each place's `transfer` is null until loaded (`transfer_loaded`), or
     * when no airport or route could be found.
     *
     * @return array{
     *     departure: array{label: string, airports: list<array<string, mixed>>}|null,
     *     transfers_enabled: bool,
     *     locations: list<array{label: string, lat: float, lng: float, airports: list<array<string, mixed>>, transfer: array<string, mixed>|null, transfer_loaded: bool}>,
     * }|null
     */
    public function forTrip(Trip $trip): ?array
    {
        $locations = $this->routeResolver->stayLocations($trip);
        $departure = $this->departure($trip, $locations);

        if ($locations === [] && $departure === null) {
            return null;
        }

        $transfersEnabled = $this->transfersEnabled();

        return [
            'departure' => $departure,
            'transfers_enabled' => $transfersEnabled,
            'locations' => array_map(
                function (array $location, int $index) use ($transfersEnabled): array {
                    $loadTransfer = $transfersEnabled && $index === 0;

                    return [
                        'label' => $location['label'],
                        'lat' => $location['lat'],
                        'lng' => $location['lng'],
                        'airports' => $this->nearestAirports($location),
                        'transfer' => $loadTransfer ? $this->transferFor($location) : null,
                        'transfer_loaded' => $loadTransfer,
                    ];
                },
                $locations,
                array_keys($locations),
            ),
        ];
    }

    /**
     * The transfer from one of the airports listed for a place, or from the
     * nearest one when no airport code is given. Returns null when transfers
     * are unavailable, the place doesn't exist, or the airport isn't one of
     * those listed for the place (so routes are never computed for arbitrary
     * airports).
     *
     * @return array{transfer: array<string, mixed>|null}|null
     */
    public function transferForLocation(Trip $trip, int $locationIndex, ?string $airportCode = null): ?array
    {
        if (! $this->transfersEnabled()) {
            return null;
        }

        $location = $this->routeResolver->stayLocations($trip)[$locationIndex] ?? null;

        if ($location === null) {
            return null;
        }

        $matches = $this->airports->nearest($location['lat'], $location['lng'], $location['country_code']);

        $match = $airportCode === null
            ? ($matches[0] ?? null)
            : collect($matches)->first(fn (array $match): bool => $match['airport']->iataCode === strtoupper($airportCode));

        if ($airportCode !== null && $match === null) {
            return null;
        }

        return [
            'transfer' => $match !== null ? $this->transfer($match['airport'], $match['distance_km'], $location) : null,
        ];
    }

    private function transfersEnabled(): bool
    {
        return filled(config('integrations.maps.drivers.geoapify.api_key'));
    }

    /**
     * The place the trip starts from, when it is mapped and is not also one
     * of the stays (a round trip's home city is listed once, as departure).
     *
     * @param  list<array{label: string, lat: float, lng: float, country_code: string|null}>  $locations
     * @return array{label: string, airports: list<array<string, mixed>>}|null
     */
    private function departure(Trip $trip, array $locations): ?array
    {
        if ($trip->isRoadTrip()) {
            return null;
        }

        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));

        if ($origin === null || $origin['lat'] === null || $origin['lng'] === null) {
            return null;
        }

        $originKey = $this->routeResolver->coordinateKey($origin['lat'], $origin['lng']);

        foreach ($locations as $location) {
            if ($this->routeResolver->coordinateKey($location['lat'], $location['lng']) === $originKey) {
                return null;
            }
        }

        $location = [
            'label' => (string) $origin['label'],
            'lat' => $origin['lat'],
            'lng' => $origin['lng'],
            'country_code' => $origin['country_code'],
        ];

        return [
            'label' => $location['label'],
            'airports' => $this->nearestAirports($location),
        ];
    }

    /**
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return list<array<string, mixed>>
     */
    private function nearestAirports(array $location): array
    {
        return array_map(
            fn (array $match): array => [
                ...$match['airport']->toArray(),
                'distance_km' => $match['distance_km'],
            ],
            $this->airports->nearest($location['lat'], $location['lng'], $location['country_code']),
        );
    }

    /**
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return array<string, mixed>|null
     */
    private function transferFor(array $location): ?array
    {
        $nearestAirport = $this->airports->nearest($location['lat'], $location['lng'], $location['country_code'])[0] ?? null;

        return $nearestAirport !== null
            ? $this->transfer($nearestAirport['airport'], $nearestAirport['distance_km'], $location)
            : null;
    }

    /**
     * Drive and public transport estimates from the nearest airport to the
     * place. Public transport is only tried for airports close enough for a
     * city transit network to plausibly connect them.
     *
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return array{
     *     airport: array{iata_code: string, name: string},
     *     drive: array{distance_km: float, minutes: int}|null,
     *     transit: array{minutes: int, rides: list<array{line: string, toward: string|null, stops: int|null, minutes: int}>}|null,
     * }
     */
    private function transfer(Airport $airport, float $straightLineKm, array $location): array
    {
        $drive = $this->route($airport, $location, 'drive');
        $transit = $straightLineKm <= (int) config('integrations.transport.transfer_transit_max_km')
            ? $this->route($airport, $location, 'approximated_transit')
            : null;

        return [
            'airport' => [
                'iata_code' => $airport->iataCode,
                'name' => $airport->name,
            ],
            'drive' => $drive === null ? null : [
                'distance_km' => $drive['distance_km'],
                'minutes' => $drive['minutes'],
            ],
            'transit' => $transit === null ? null : [
                'minutes' => $transit['minutes'],
                'rides' => $transit['rides'],
            ],
        ];
    }

    /**
     * Cached route lookup. Failures (often "too far from a transit stop" for
     * public transport) are cached for a shorter time so they are retried
     * later without hitting Geoapify on every page view.
     *
     * @param  array{label: string, lat: float, lng: float, country_code: string|null}  $location
     * @return array{distance_km: float, minutes: int, rides: list<array{line: string, toward: string|null, stops: int|null, minutes: int}>}|null
     */
    private function route(Airport $airport, array $location, string $mode): ?array
    {
        $cacheKey = sprintf(
            'transport:route:v1:%s:%s:%s',
            $mode,
            $airport->iataCode,
            $this->routeResolver->coordinateKey($location['lat'], $location['lng']),
        );

        /** @var array{route: array{distance_km: float, minutes: int, rides: list<array{line: string, toward: string|null, stops: int|null, minutes: int}>}|null}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached['route'];
        }

        try {
            $result = $this->routing->getRoute([
                ['lat' => $airport->latitude, 'lng' => $airport->longitude],
                ['lat' => $location['lat'], 'lng' => $location['lng']],
            ], $mode);

            $route = [
                'distance_km' => $result->distanceKm,
                'minutes' => (int) max(1, round($result->durationSeconds / 60)),
                'rides' => $this->rides($result),
            ];
        } catch (RoadTripException) {
            $route = null;
        }

        Cache::put(
            $cacheKey,
            ['route' => $route],
            (int) config($route === null ? 'integrations.transport.failure_cache_ttl' : 'integrations.transport.cache_ttl'),
        );

        return $route;
    }

    /**
     * The vehicle legs of a public transport route. Geoapify only describes
     * them in the step instruction text, e.g. "Take the 543B toward 543B:
     * Anand Vihar ISBT → IGI Airport. (3 stops)".
     *
     * @return list<array{line: string, toward: string|null, stops: int|null, minutes: int}>
     */
    private function rides(RouteResult $result): array
    {
        $rides = [];

        foreach ($result->legs as $leg) {
            $steps = is_array($leg['steps'] ?? null) ? $leg['steps'] : [];

            foreach ($steps as $step) {
                $text = is_array($step) ? (string) ($step['instruction']['text'] ?? '') : '';

                if (! preg_match('/^(?:Transfer to take|Take) the (.+?)(?: toward (.+?))?\.(?: \((\d+) stops?\))?$/u', $text, $matches)) {
                    continue;
                }

                $line = trim($matches[1]);
                $toward = isset($matches[2]) && $matches[2] !== '' ? trim($matches[2]) : null;

                if ($toward !== null && str_starts_with($toward, $line.': ')) {
                    $toward = substr($toward, strlen($line) + 2);
                }

                $rides[] = [
                    'line' => $line,
                    'toward' => $toward,
                    'stops' => isset($matches[3]) ? (int) $matches[3] : null,
                    'minutes' => (int) max(1, round(((float) ($step['time'] ?? 0)) / 60)),
                ];
            }
        }

        return $rides;
    }
}
