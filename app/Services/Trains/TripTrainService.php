<?php

namespace App\Services\Trains;

use App\Enums\TripScope;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TripTrainService
{
    private const string DIRECT_ONLY_NOTICE = 'Only direct Indian Railways trains are shown. Connecting journeys with a change are not included yet.';

    public function __construct(
        private RailRadarClient $client,
        private RailwayStationResolver $stationResolver,
        private NearestRailheadResolver $railheadResolver,
        private TripRouteResolver $routeResolver,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forTrip(Trip $trip): ?array
    {
        if (config('integrations.trains.driver') !== 'railradar') {
            return [
                'available' => false,
                'reason' => 'driver_disabled',
                'message' => 'Set TRAIN_DRIVER=railradar in your .env file to enable train timings.',
            ];
        }

        if (! filled(config('integrations.trains.drivers.railradar.api_key'))) {
            return [
                'available' => false,
                'reason' => 'driver_disabled',
                'message' => 'Add RAILRADAR_API_KEY to your .env file to see Indian rail options.',
            ];
        }

        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));
        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));

        $tripScope = $trip->trip_scope ?? Trip::resolveTripScope($origin, $destination);

        if ($tripScope !== TripScope::Domestic) {
            return [
                'available' => false,
                'reason' => 'not_domestic',
                'message' => 'Train timings are shown for domestic Indian trips only.',
            ];
        }

        if ($origin === null || $destination === null) {
            return [
                'available' => false,
                'reason' => 'missing_coordinates',
                'message' => 'Pick both origin and destination from search to see train options.',
            ];
        }

        if ($origin['lat'] === null || $origin['lng'] === null || $destination['lat'] === null || $destination['lng'] === null) {
            return [
                'available' => false,
                'reason' => 'missing_coordinates',
                'message' => 'Pick both origin and destination from search to see train options.',
            ];
        }

        $destinationRailhead = $this->railheadResolver->forLocation($destination);
        $originStation = $this->stationResolver->resolve($origin);
        $destinationStation = $this->stationResolver->resolve($destination);

        if ($destinationStation === null && $destinationRailhead !== null) {
            $destinationStation = $destinationRailhead['station'];
        }

        if ($originStation === null || $destinationStation === null) {
            return [
                'available' => false,
                'reason' => 'stations_unresolved',
                'message' => 'Could not match your route to nearby railway stations. Try picking a major city as origin or destination.',
                'origin_label' => $origin['label'] ?? null,
                'destination_label' => $destination['label'] ?? null,
                'destination_railhead' => $destinationRailhead,
                'direct_only_notice' => self::DIRECT_ONLY_NOTICE,
            ];
        }

        if ($originStation['code'] === $destinationStation['code'] && count($this->routeResolver->travelLegs($trip)) <= 1) {
            return [
                'available' => false,
                'reason' => 'same_station',
                'message' => 'Origin and destination resolve to the same railway station.',
                'from_station' => $originStation,
                'to_station' => $destinationStation,
                'destination_railhead' => $destinationRailhead,
                'direct_only_notice' => self::DIRECT_ONLY_NOTICE,
            ];
        }

        $travelLegs = $this->routeResolver->travelLegs($trip);
        $routeSummary = $this->routeResolver->summary($trip);

        $cacheTtl = (int) config('integrations.trains.cache_ttl', 43200);
        $cacheKey = sprintf(
            'trip_trains:v4:%s',
            md5(json_encode($travelLegs) ?: 'simple'),
        );

        return Cache::remember($cacheKey, $cacheTtl, function () use (

            $travelLegs,
            $routeSummary,
            $origin,
            $destination,
            $destinationRailhead,
        ): array {
            $formattedLegs = [];
            $totalTrains = 0;
            $usesRailheadFallback = false;

            foreach ($travelLegs as $travelLeg) {
                /** @var array<string, mixed> $from */
                $from = $travelLeg['from'];
                /** @var array<string, mixed> $to */
                $to = $travelLeg['to'];

                $fromStation = $this->stationResolver->resolve($from);
                $toStation = $this->stationResolver->resolve($to);
                $legRailhead = $this->railheadResolver->forLocation($to);

                if ($toStation === null && $legRailhead !== null) {
                    $toStation = $legRailhead['station'];
                }

                if ($fromStation === null || $toStation === null) {
                    $formattedLegs[] = [
                        'sequence' => $travelLeg['sequence'],
                        'direction' => $travelLeg['direction'],
                        'date' => $travelLeg['travel_date'],
                        'from_station' => $fromStation ?? ['code' => '', 'name' => $from['label'] ?? 'Start'],
                        'to_station' => $toStation ?? ['code' => '', 'name' => $to['label'] ?? 'End'],
                        'from_label' => $travelLeg['from_label'] ?? null,
                        'to_label' => $travelLeg['to_label'] ?? null,
                        'route_label' => $travelLeg['route_label'],
                        'available' => false,
                        'reason' => 'stations_unresolved',
                        'message' => 'Could not match this leg to railway stations.',
                        'count' => 0,
                        'trains' => [],
                    ];

                    continue;
                }

                $leg = $this->fetchLegWithRailheadFallback(
                    $fromStation,
                    $toStation,
                    $travelLeg['travel_date'] ?? null,
                    (string) $travelLeg['direction'],
                    $travelLeg['from_label'] ?? null,
                    $travelLeg['to_label'] ?? null,
                    $legRailhead,
                );

                $formattedLegs[] = [
                    'sequence' => $travelLeg['sequence'],
                    ...$leg,
                ];

                $totalTrains += (int) ($leg['count'] ?? 0);
                $usesRailheadFallback = $usesRailheadFallback || ($leg['search_mode'] ?? 'direct') === 'railhead';
            }

            $outbound = $formattedLegs[0] ?? null;
            $return = null;

            foreach (array_reverse($formattedLegs) as $leg) {
                if (($leg['direction'] ?? '') === 'return') {
                    $return = $leg;
                    break;
                }
            }

            $payload = [
                'origin_label' => $origin['label'] ?? null,
                'destination_label' => $destination['label'] ?? null,
                'from_station' => $this->stationResolver->resolve($origin),
                'to_station' => $this->stationResolver->resolve($destination),
                'destination_railhead' => $destinationRailhead,
                'route_mode' => $routeSummary['route_mode'],
                'route_label' => $routeSummary['route_label'],
                'leg_count' => count($formattedLegs),
                'legs' => $formattedLegs,
                'outbound' => $outbound,
                'return' => $return ?: ($formattedLegs[1] ?? null),
                'direct_only_notice' => self::DIRECT_ONLY_NOTICE,
                'uses_railhead_fallback' => $usesRailheadFallback,
            ];

            if ($totalTrains === 0) {
                return [
                    ...$payload,
                    'available' => false,
                    'reason' => 'no_trains',
                    'message' => $this->noTrainsMessage($destinationRailhead, $destination['label'] ?? null),
                ];
            }

            return [
                ...$payload,
                'available' => true,
                'disclaimer' => 'Schedules from RailRadar (crowdsourced). Confirm times, halts, and availability on IRCTC before booking.',
                'source' => 'RailRadar',
            ];
        });
    }

    /**
     * @param  array{code: string, name: string}  $fromStation
     * @param  array{code: string, name: string}  $toStation
     * @param  array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }|null  $destinationRailhead
     * @return array<string, mixed>
     */
    private function fetchLegWithRailheadFallback(
        array $fromStation,
        array $toStation,
        ?string $travelDate,
        string $direction,
        ?string $fromLabel,
        ?string $toLabel,
        ?array $destinationRailhead,
    ): array {
        $direct = $this->fetchLeg(
            $fromStation,
            $toStation,
            $travelDate,
            $direction,
            $fromLabel,
            $toLabel,
        );

        if (($direct['count'] ?? 0) > 0) {
            return [
                ...$direct,
                'search_mode' => 'direct',
            ];
        }

        if ($destinationRailhead === null) {
            return [
                ...$direct,
                'search_mode' => 'direct',
            ];
        }

        $railheadStation = $destinationRailhead['station'];

        if ($direction === 'outbound') {
            if ($railheadStation['code'] === $toStation['code']) {
                return $this->finalizeFailedLeg($direct, $destinationRailhead, $direction);
            }

            $railheadLeg = $this->fetchLeg(
                $fromStation,
                $railheadStation,
                $travelDate,
                $direction,
                $fromLabel,
                $destinationRailhead['place_label'],
            );
        } else {
            if ($railheadStation['code'] === $fromStation['code']) {
                return $this->finalizeFailedLeg($direct, $destinationRailhead, $direction);
            }

            $railheadLeg = $this->fetchLeg(
                $railheadStation,
                $toStation,
                $travelDate,
                $direction,
                $destinationRailhead['place_label'],
                $toLabel,
            );
        }

        if (($railheadLeg['count'] ?? 0) > 0) {
            return [
                ...$railheadLeg,
                'search_mode' => 'railhead',
                'railhead' => $destinationRailhead,
                'direct_leg' => $direct,
                'message' => $this->railheadSuccessMessage($destinationRailhead, $direction),
            ];
        }

        return $this->finalizeFailedLeg($direct, $destinationRailhead, $direction, $railheadLeg);
    }

    /**
     * @param  array<string, mixed>  $direct
     * @param  array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }  $destinationRailhead
     * @param  array<string, mixed>|null  $railheadAttempt
     * @return array<string, mixed>
     */
    private function finalizeFailedLeg(
        array $direct,
        array $destinationRailhead,
        string $direction,
        ?array $railheadAttempt = null,
    ): array {
        return [
            ...$direct,
            'search_mode' => 'direct',
            'railhead' => $destinationRailhead,
            'railhead_attempt' => $railheadAttempt,
            'message' => $this->railheadFailureMessage($destinationRailhead, $direction),
        ];
    }

    /**
     * @param  array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }  $destinationRailhead
     */
    private function railheadSuccessMessage(array $destinationRailhead, string $direction): string
    {
        $place = $destinationRailhead['place_label'];
        $station = sprintf('%s (%s)', $destinationRailhead['station']['name'], $destinationRailhead['station']['code']);

        if ($direction === 'return') {
            return sprintf(
                'No direct train from %s. Showing return options from nearest mainline station %s.',
                $place,
                $station,
            );
        }

        return sprintf(
            'No direct train to %s. Showing trains to nearest mainline station %s.',
            $place,
            $station,
        );
    }

    /**
     * @param  array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }|null  $destinationRailhead
     */
    private function noTrainsMessage(?array $destinationRailhead, ?string $destinationLabel): string
    {
        if ($destinationRailhead !== null) {
            return sprintf(
                'No direct or mainline rail options found for your dates. For %s, the nearest mainline station is %s (%s). %s',
                $destinationRailhead['place_label'],
                $destinationRailhead['station']['name'],
                $destinationRailhead['station']['code'],
                $destinationRailhead['last_mile'],
            );
        }

        if ($destinationLabel !== null) {
            return sprintf(
                'No direct trains found for your outbound or return dates to %s. Try adjusting dates or book on IRCTC.',
                $destinationLabel,
            );
        }

        return 'No direct trains found for your outbound or return dates. Try adjusting dates or book on IRCTC.';
    }

    /**
     * @param  array{
     *     place_key: string,
     *     place_label: string,
     *     station: array{code: string, name: string},
     *     last_mile: string,
     * }  $destinationRailhead
     */
    private function railheadFailureMessage(array $destinationRailhead, string $direction): string
    {
        $station = sprintf('%s (%s)', $destinationRailhead['station']['name'], $destinationRailhead['station']['code']);

        if ($direction === 'return') {
            return sprintf(
                'No direct or mainline return trains found for your date. Leave from %s, then continue to your origin. %s',
                $station,
                $destinationRailhead['last_mile'],
            );
        }

        return sprintf(
            'No direct trains to %s. Nearest mainline station is %s. %s',
            $destinationRailhead['place_label'],
            $station,
            $destinationRailhead['last_mile'],
        );
    }

    /**
     * @param  array{code: string, name: string}  $fromStation
     * @param  array{code: string, name: string}  $toStation
     * @return array<string, mixed>
     */
    private function fetchLeg(
        array $fromStation,
        array $toStation,
        ?string $travelDate,
        string $direction,
        ?string $fromLabel,
        ?string $toLabel,
    ): array {
        $response = $this->client->trainsBetween(
            $fromStation['code'],
            $toStation['code'],
            $travelDate,
        );

        $leg = [
            'direction' => $direction,
            'date' => $travelDate,
            'from_station' => $fromStation,
            'to_station' => $toStation,
            'from_label' => $fromLabel,
            'to_label' => $toLabel,
            'route_label' => sprintf('%s → %s', $fromStation['name'], $toStation['name']),
        ];

        if ($response->status() === 404) {
            return [
                ...$leg,
                'available' => false,
                'reason' => 'no_trains',
                'message' => $travelDate !== null
                    ? 'No direct trains found on this date for this direction.'
                    : 'No direct trains found for this direction.',
                'count' => 0,
                'trains' => [],
            ];
        }

        if ($response->failed()) {
            Log::warning('RailRadar trains-between request failed.', [
                'status' => $response->status(),
                'from' => $fromStation['code'],
                'to' => $toStation['code'],
                'direction' => $direction,
            ]);

            return [
                ...$leg,
                'available' => false,
                'reason' => 'fetch_failed',
                'message' => 'Train timings are temporarily unavailable for this direction.',
                'count' => 0,
                'trains' => [],
            ];
        }

        /** @var list<array<string, mixed>> $trains */
        $trains = $response->json('data.trains') ?? [];
        $maxResults = (int) config('integrations.trains.max_results', 12);

        $formattedTrains = collect($trains)
            ->take($maxResults)
            ->map(fn (array $entry): array => $this->formatTrainEntry($entry, $fromStation, $toStation, $travelDate))
            ->values()
            ->all();

        return [
            ...$leg,
            'available' => true,
            'count' => count($trains),
            'trains' => $formattedTrains,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{code: string, name: string}  $fromStation
     * @param  array{code: string, name: string}  $toStation
     * @return array<string, mixed>
     */
    private function formatTrainEntry(
        array $entry,
        array $fromStation,
        array $toStation,
        ?string $travelDate,
    ): array {
        /** @var array<string, mixed> $train */
        $train = $entry['train'] ?? [];
        /** @var array<string, mixed> $from */
        $from = $entry['from'] ?? [];
        /** @var array<string, mixed> $to */
        $to = $entry['to'] ?? [];
        /** @var array<string, mixed> $live */
        $live = $entry['live'] ?? [];

        $durationMinutes = is_numeric($entry['duration'] ?? null) ? (int) $entry['duration'] : null;
        $fromDay = is_numeric($from['day'] ?? null) ? (int) $from['day'] : null;
        $toDay = is_numeric($to['day'] ?? null) ? (int) $to['day'] : null;

        return [
            'number' => (string) ($train['number'] ?? ''),
            'name' => (string) ($train['name'] ?? ''),
            'type' => isset($train['type']) ? (string) $train['type'] : null,
            'category' => isset($train['category']) ? (string) $train['category'] : null,
            'departure' => isset($from['departure']) ? (string) $from['departure'] : null,
            'arrival' => isset($to['arrival']) ? (string) $to['arrival'] : null,
            'departure_day' => $fromDay,
            'arrival_day' => $toDay,
            'day_offset' => ($fromDay !== null && $toDay !== null) ? max(0, $toDay - $fromDay) : null,
            'from_sequence' => is_numeric($from['sequence'] ?? null) ? (int) $from['sequence'] : null,
            'to_sequence' => is_numeric($to['sequence'] ?? null) ? (int) $to['sequence'] : null,
            'duration_minutes' => $durationMinutes,
            'duration_label' => $this->formatDuration($durationMinutes),
            'run_days' => $this->formatRunDays($train['runDays'] ?? []),
            'runs_daily' => $this->runsDaily($train['runDays'] ?? []),
            'distance_km' => is_numeric($entry['distance'] ?? null) ? (float) $entry['distance'] : null,
            'total_halts_between' => is_numeric($entry['totalHaltsBetween'] ?? null)
                ? (int) $entry['totalHaltsBetween']
                : null,
            'travel_date' => $travelDate,
            'from_station' => $fromStation,
            'to_station' => $toStation,
            'live' => $this->formatLiveStatus($live),
        ];
    }

    /**
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>|null
     */
    private function formatLiveStatus(array $live): ?array
    {
        if ($live === []) {
            return null;
        }

        return [
            'type' => isset($live['type']) ? (string) $live['type'] : null,
            'platform' => isset($live['platform']) ? (string) $live['platform'] : null,
            'delay_minutes' => is_numeric($live['delayMinutes'] ?? null) ? (int) $live['delayMinutes'] : null,
            'expected_arrival' => isset($live['expectedArrivalTime']) ? (string) $live['expectedArrivalTime'] : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function formatRunDays(mixed $runDays): array
    {
        if (! is_array($runDays)) {
            return [];
        }

        $labels = [
            'mon' => 'Mon',
            'tue' => 'Tue',
            'wed' => 'Wed',
            'thu' => 'Thu',
            'fri' => 'Fri',
            'sat' => 'Sat',
            'sun' => 'Sun',
        ];

        return collect($runDays)
            ->map(fn (mixed $day): ?string => $labels[strtolower((string) $day)] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function runsDaily(mixed $runDays): bool
    {
        return is_array($runDays) && count($runDays) === 7;
    }

    private function formatDuration(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return sprintf('%dm', $remainingMinutes);
        }

        if ($remainingMinutes === 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }
}
