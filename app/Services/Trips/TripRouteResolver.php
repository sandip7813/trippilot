<?php

namespace App\Services\Trips;

use App\Enums\TripRouteMode;
use App\Models\Trip;
use Illuminate\Support\Carbon;

class TripRouteResolver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function normalizedWaypoints(Trip $trip): array
    {
        $raw = Trip::coerceStructuredArray($trip->getAttribute('waypoints')) ?? [];

        return collect($raw)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $waypoint, int $index): array {
                $location = Trip::normalizeLocation($waypoint['location'] ?? null);

                return [
                    'sequence' => is_numeric($waypoint['sequence'] ?? null)
                        ? (int) $waypoint['sequence']
                        : $index + 1,
                    'location' => $location,
                    'nights' => is_numeric($waypoint['nights'] ?? null)
                        ? max(0, (int) $waypoint['nights'])
                        : null,
                    'notes' => isset($waypoint['notes']) ? (string) $waypoint['notes'] : null,
                ];
            })
            ->sortBy('sequence')
            ->values()
            ->all();
    }

    public function routeMode(Trip $trip): TripRouteMode
    {
        $waypoints = $this->normalizedWaypoints($trip);

        if ($trip->route_mode instanceof TripRouteMode) {
            if ($trip->route_mode === TripRouteMode::Simple && count($waypoints) >= 2) {
                return TripRouteMode::MultiCity;
            }

            return $trip->route_mode;
        }

        return count($waypoints) > 1
            ? TripRouteMode::MultiCity
            : TripRouteMode::Simple;
    }

    public function returnsToOrigin(Trip $trip): bool
    {
        if ($trip->returns_to_origin !== null) {
            return (bool) $trip->returns_to_origin;
        }

        return true;
    }

    /**
     * Ordered route points: origin, optional middle waypoints, final destination.
     *
     * @return list<array<string, mixed>>
     */
    public function routePoints(Trip $trip): array
    {
        $origin = Trip::normalizeLocation($trip->getAttribute('origin'));
        $destination = Trip::normalizeLocation($trip->getAttribute('destination'));
        $waypoints = $this->normalizedWaypoints($trip);
        $useMultiStopRoute = count($waypoints) >= 1
            || $this->routeMode($trip) === TripRouteMode::MultiCity;

        if (! $useMultiStopRoute) {
            $points = array_values(array_filter([$origin, $destination], fn (?array $point): bool => $point !== null));

            return $this->dedupeConsecutivePoints($points);
        }

        $points = [];

        if ($origin !== null) {
            $points[] = $origin;
        }

        foreach ($waypoints as $waypoint) {
            if ($waypoint['location'] !== null) {
                $points[] = $waypoint['location'];
            }
        }

        if ($destination !== null) {
            $points[] = $destination;
        }

        return $this->dedupeConsecutivePoints($points);
    }

    /**
     * Travel legs between consecutive route points.
     *
     * @return list<array<string, mixed>>
     */
    public function travelLegs(Trip $trip): array
    {
        $points = $this->routePoints($trip);

        if (count($points) < 2) {
            return [];
        }

        $routeMode = $this->routeMode($trip);
        $returnsToOrigin = $this->returnsToOrigin($trip);
        $origin = $points[0] ?? null;

        if ($routeMode === TripRouteMode::MultiCity && $returnsToOrigin && $origin !== null) {
            $last = $points[array_key_last($points)] ?? null;

            if ($last !== null && ! $this->locationsMatch($last, $origin)) {
                $points[] = $origin;
            }
        }

        $waypoints = $this->normalizedWaypoints($trip);
        $stayNights = $this->assignedStayNights($trip, $waypoints);
        $dates = $this->assignLegDates($trip, $points, $waypoints, $stayNights);

        $legs = [];

        for ($index = 0; $index < count($points) - 1; $index++) {
            $from = $points[$index];
            $to = $points[$index + 1];
            $sequence = $index + 1;
            $isReturn = $returnsToOrigin
                && $routeMode === TripRouteMode::MultiCity
                && $index === count($points) - 2
                && $origin !== null
                && $this->locationsMatch($to, $origin);

            $legs[] = [
                'sequence' => $sequence,
                'direction' => $this->legDirection($routeMode, $sequence, $isReturn),
                'from' => $from,
                'to' => $to,
                'from_label' => $from['label'] ?? null,
                'to_label' => $to['label'] ?? null,
                'route_label' => sprintf('%s → %s', $from['label'] ?? 'Start', $to['label'] ?? 'End'),
                'travel_date' => $dates[$index] ?? null,
            ];
        }

        if ($routeMode === TripRouteMode::Simple && $returnsToOrigin && count($points) >= 2) {
            $from = $points[array_key_last($points)];
            $to = $points[0];

            $legs[] = [
                'sequence' => count($legs) + 1,
                'direction' => 'return',
                'from' => $from,
                'to' => $to,
                'from_label' => $from['label'] ?? null,
                'to_label' => $to['label'] ?? null,
                'route_label' => sprintf('%s → %s', $from['label'] ?? 'Start', $to['label'] ?? 'Home'),
                'travel_date' => $trip->end_date?->toDateString(),
            ];
        }

        return $legs;
    }

    /**
     * Stay segments for weather and itinerary grouping.
     *
     * @return list<array<string, mixed>>
     */
    public function staySegments(Trip $trip): array
    {
        $points = $this->routePoints($trip);
        $waypoints = $this->normalizedWaypoints($trip);
        $stayNights = $this->assignedStayNights($trip, $waypoints);

        if ($points === []) {
            return [];
        }

        $startDate = $trip->start_date !== null
            ? Carbon::parse($trip->start_date)->startOfDay()
            : null;

        if ($startDate === null) {
            return [];
        }

        $segments = [];
        $currentDate = $startDate->copy();

        if ($this->routeMode($trip) === TripRouteMode::Simple) {
            $destination = $points[1] ?? $points[0] ?? null;

            if ($destination === null) {
                return [];
            }

            $endDate = Carbon::parse($trip->end_date ?? $trip->start_date)->startOfDay();

            return [[
                'sequence' => 1,
                'location' => $destination,
                'label' => $destination['label'] ?? null,
                'date_from' => $currentDate->toDateString(),
                'date_to' => $endDate->toDateString(),
                'nights' => max(0, (int) $currentDate->diffInDays($endDate)),
            ]];
        }

        $stayIndex = 0;

        foreach ($waypoints as $waypoint) {
            if ($waypoint['location'] === null) {
                continue;
            }

            $nights = $stayNights[$stayIndex] ?? 1;
            $stayIndex++;
            $dateFrom = $currentDate->copy();
            $dateTo = $currentDate->copy()->addDays(max(0, $nights - 1));

            $segments[] = [
                'sequence' => $waypoint['sequence'],
                'location' => $waypoint['location'],
                'label' => $waypoint['location']['label'] ?? null,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'nights' => $nights,
                'notes' => $waypoint['notes'],
            ];

            $currentDate = $dateTo->copy()->addDay();
        }

        return $segments;
    }

    /**
     * Human-readable route chain for maps and overview, including return to origin when enabled.
     *
     * @return list<string>
     */
    public function routeDisplayPointLabels(Trip $trip): array
    {
        $points = $this->routePoints($trip);

        $labels = collect($points)
            ->map(fn (array $point): ?string => $point['label'] ?? null)
            ->filter()
            ->values()
            ->all();

        if (! $this->returnsToOrigin($trip) || count($points) < 2) {
            return $labels;
        }

        $origin = $points[0];
        $last = $points[array_key_last($points)];
        $originLabel = $origin['label'] ?? null;

        if ($originLabel !== null && ! $this->locationsMatch($last, $origin)) {
            $labels[] = $originLabel;
        }

        return $labels;
    }

    /**
     * Ordered stops for route overview UI: origin, city stays, optional return home.
     *
     * @return list<array<string, mixed>>
     */
    public function routeOverviewStops(Trip $trip): array
    {
        $displayLabels = $this->routeDisplayPointLabels($trip);

        if ($displayLabels === []) {
            return [];
        }

        $points = $this->routePoints($trip);
        $origin = $points[0] ?? null;
        $staySegments = $this->staySegments($trip);
        $legs = $this->travelLegs($trip);
        $returnLeg = collect($legs)->first(fn (array $leg): bool => ($leg['direction'] ?? null) === 'return')
            ?? collect($legs)->last();
        $stops = [];
        $matchedSegmentIndexes = [];

        foreach ($displayLabels as $index => $label) {
            if (! is_string($label) || trim($label) === '') {
                continue;
            }

            $isOrigin = $index === 0;
            $isReturn = ! $isOrigin
                && $this->returnsToOrigin($trip)
                && $index === count($displayLabels) - 1
                && is_array($origin)
                && $this->labelsMatch($label, (string) ($origin['label'] ?? ''));

            if ($isOrigin) {
                $stops[] = [
                    'kind' => 'origin',
                    'sequence' => 0,
                    'label' => $label,
                    'nights' => null,
                    'arrival_date' => null,
                    'departure_date' => $trip->start_date?->toDateString(),
                ];

                continue;
            }

            if ($isReturn) {
                $stops[] = [
                    'kind' => 'return',
                    'sequence' => count($stops),
                    'label' => $label,
                    'nights' => null,
                    'arrival_date' => $returnLeg['travel_date']
                        ?? $trip->end_date?->toDateString(),
                    'departure_date' => null,
                ];

                continue;
            }

            $point = collect($points)->first(
                fn (array $point): bool => $this->labelsMatch($label, (string) ($point['label'] ?? '')),
            );

            $segment = is_array($point)
                ? $this->matchingStaySegment($staySegments, $point, $matchedSegmentIndexes)
                : $this->matchingStaySegmentByLabel($staySegments, $label, $matchedSegmentIndexes);

            if ($segment !== null) {
                $matchedSegmentIndexes[] = $segment['index'];
            }

            $dateTo = isset($segment['date_to'])
                ? Carbon::parse((string) $segment['date_to'])
                : null;

            $stops[] = [
                'kind' => 'stay',
                'sequence' => count($stops),
                'label' => $label,
                'nights' => $segment['nights'] ?? null,
                'arrival_date' => $segment['date_from'] ?? null,
                'departure_date' => $dateTo?->copy()->addDay()->toDateString(),
            ];
        }

        return $stops;
    }

    /**
     * @param  list<array<string, mixed>>  $staySegments
     * @param  list<int>  $excludeIndexes
     * @return array{index: int, label: string|null, nights: int|null, date_from: string|null, date_to: string|null}|null
     */
    private function matchingStaySegmentByLabel(array $staySegments, string $label, array $excludeIndexes): ?array
    {
        foreach ($staySegments as $index => $segment) {
            if (in_array($index, $excludeIndexes, true)) {
                continue;
            }

            $segmentLabel = $segment['label'] ?? null;

            if ($segmentLabel !== null && $this->labelsMatch($label, $segmentLabel)) {
                return [
                    'index' => $index,
                    'label' => $segmentLabel,
                    'nights' => $segment['nights'] ?? null,
                    'date_from' => $segment['date_from'] ?? null,
                    'date_to' => $segment['date_to'] ?? null,
                ];
            }
        }

        return null;
    }

    private function labelsMatch(string $left, string $right): bool
    {
        return strtolower(trim($left)) === strtolower(trim($right));
    }

    /**
     * @param  list<array<string, mixed>>  $staySegments
     * @param  array<string, mixed>  $point
     * @param  list<int>  $excludeIndexes
     * @return array{index: int, label: string|null, nights: int|null, date_from: string|null, date_to: string|null}|null
     */
    private function matchingStaySegment(array $staySegments, array $point, array $excludeIndexes): ?array
    {
        foreach ($staySegments as $index => $segment) {
            if (in_array($index, $excludeIndexes, true)) {
                continue;
            }

            /** @var array<string, mixed>|null $location */
            $location = $segment['location'] ?? null;

            if (is_array($location) && $this->locationsMatch($location, $point)) {
                return [
                    'index' => $index,
                    'label' => $segment['label'] ?? $location['label'] ?? null,
                    'nights' => $segment['nights'] ?? null,
                    'date_from' => $segment['date_from'] ?? null,
                    'date_to' => $segment['date_to'] ?? null,
                ];
            }
        }

        foreach ($staySegments as $index => $segment) {
            if (in_array($index, $excludeIndexes, true)) {
                continue;
            }

            $label = $segment['label'] ?? null;
            $pointLabel = $point['label'] ?? null;

            if ($label !== null && $pointLabel !== null
                && strtolower(trim($label)) === strtolower(trim($pointLabel))) {
                return [
                    'index' => $index,
                    'label' => $label,
                    'nights' => $segment['nights'] ?? null,
                    'date_from' => $segment['date_from'] ?? null,
                    'date_to' => $segment['date_to'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Trip $trip): array
    {
        $points = $this->routePoints($trip);
        $legs = $this->travelLegs($trip);
        $segments = $this->staySegments($trip);
        $displayLabels = $this->routeDisplayPointLabels($trip);
        $overviewStops = $this->routeOverviewStops($trip);

        return [
            'route_mode' => $this->routeMode($trip)->value,
            'returns_to_origin' => $this->returnsToOrigin($trip),
            'city_count' => max(0, count($points) - 1),
            'stop_count' => count($this->normalizedWaypoints($trip)),
            'leg_count' => count($legs),
            'stay_segments' => $segments,
            'route_stops' => $overviewStops,
            'route_points' => collect($points)
                ->map(fn (array $point): ?string => $point['label'] ?? null)
                ->filter()
                ->values()
                ->all(),
            'route_display_points' => $displayLabels,
            'route_label' => implode(' → ', $displayLabels),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $waypoints
     * @return list<int>
     */
    private function assignedStayNights(Trip $trip, array $waypoints): array
    {
        $explicit = collect($waypoints)
            ->map(fn (array $waypoint): ?int => $waypoint['nights'])
            ->all();

        if ($explicit !== [] && collect($explicit)->every(fn (?int $nights): bool => $nights !== null && $nights > 0)) {
            return array_map(fn (?int $nights): int => max(1, (int) $nights), $explicit);
        }

        $stayCount = count($waypoints);

        if ($stayCount === 0) {
            return [];
        }

        $totalDays = $this->dayCount($trip);
        $travelLegCount = max(0, count($this->routePoints($trip)) - 1);

        if ($this->routeMode($trip) === TripRouteMode::MultiCity && $this->returnsToOrigin($trip)) {
            $travelLegCount++;
        }

        $stayDays = max($stayCount, $totalDays - min($travelLegCount, $totalDays - 1));
        $base = intdiv($stayDays, $stayCount);
        $remainder = $stayDays % $stayCount;

        return collect(range(0, $stayCount - 1))
            ->map(fn (int $index): int => $base + ($index < $remainder ? 1 : 0))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @param  list<array<string, mixed>>  $waypoints
     * @param  list<int>  $stayNights
     * @return list<string|null>
     */
    private function assignLegDates(Trip $trip, array $points, array $waypoints, array $stayNights): array
    {
        if ($trip->start_date === null) {
            return array_fill(0, max(0, count($points) - 1), null);
        }

        $currentDate = Carbon::parse($trip->start_date)->startOfDay();
        $endDate = Carbon::parse($trip->end_date ?? $trip->start_date)->startOfDay();
        $legCount = max(0, count($points) - 1);
        $dates = [];

        for ($index = 0; $index < $legCount; $index++) {
            if ($index === $legCount - 1 && $this->returnsToOrigin($trip) && $this->routeMode($trip) === TripRouteMode::MultiCity) {
                $dates[] = $endDate->toDateString();
            } else {
                $dates[] = $currentDate->toDateString();
            }

            if ($index < count($waypoints)) {
                $nights = $stayNights[$index] ?? 1;
                $currentDate = $currentDate->copy()->addDays(max(1, $nights));
            } elseif ($index === 0 && $this->routeMode($trip) === TripRouteMode::Simple) {
                break;
            }
        }

        while (count($dates) < $legCount) {
            $dates[] = null;
        }

        return $dates;
    }

    private function dayCount(Trip $trip): int
    {
        if ($trip->start_date !== null && $trip->end_date !== null) {
            return max(1, (int) $trip->start_date->diffInDays($trip->end_date) + 1);
        }

        return 5;
    }

    private function legDirection(TripRouteMode $routeMode, int $sequence, bool $isReturn): string
    {
        if ($isReturn) {
            return 'return';
        }

        if ($routeMode === TripRouteMode::MultiCity && $sequence > 1) {
            return 'inter_city';
        }

        return $sequence === 1 ? 'outbound' : 'return';
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function dedupeConsecutivePoints(array $points): array
    {
        $deduped = [];

        foreach ($points as $point) {
            $last = $deduped !== [] ? $deduped[array_key_last($deduped)] : null;

            if ($last !== null && $this->locationsMatch($last, $point)) {
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
        if (($left['place_id'] ?? null) !== null && ($right['place_id'] ?? null) !== null) {
            return $left['place_id'] === $right['place_id'];
        }

        if ($left['lat'] !== null && $left['lng'] !== null && $right['lat'] !== null && $right['lng'] !== null) {
            return abs($left['lat'] - $right['lat']) < 0.0001
                && abs($left['lng'] - $right['lng']) < 0.0001;
        }

        return strtolower(trim((string) ($left['label'] ?? ''))) === strtolower(trim((string) ($right['label'] ?? '')));
    }
}
