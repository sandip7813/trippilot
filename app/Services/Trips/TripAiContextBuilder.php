<?php

namespace App\Services\Trips;

use App\Models\Trip;

class TripAiContextBuilder
{
    public function __construct(private TripRouteResolver $routeResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Trip $trip): array
    {
        $itinerary = Trip::coerceStructuredArray($trip->getAttribute('itinerary')) ?? Trip::emptyItinerary();

        $context = [
            'trip_id' => (string) $trip->id,
            'title' => $trip->title,
            'type' => $trip->type->value,
            'type_label' => $trip->type->label(),
            'travel_style' => $trip->travel_style?->value,
            'travel_style_label' => $trip->travel_style?->label(),
            'origin' => Trip::normalizeLocation($trip->getAttribute('origin')),
            'destination' => Trip::normalizeLocation($trip->getAttribute('destination')),
            'route_mode' => $this->routeResolver->routeMode($trip)->value,
            'returns_to_origin' => $this->routeResolver->returnsToOrigin($trip),
            'waypoints' => $this->routeResolver->normalizedWaypoints($trip),
            'route_summary' => $this->routeResolver->summary($trip),
            'travel_legs' => $this->routeResolver->travelLegs($trip),
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'day_count' => $this->dayCount($trip),
            'budget' => $trip->budget,
            'travelers' => $trip->travelers,
            'notes' => $trip->notes,
            'itinerary' => $itinerary,
            'has_itinerary' => $trip->hasGeneratedItinerary(),
        ];

        if ($trip->isRoadTrip()) {
            $context['road_profile'] = $this->roadProfileContext($trip);
            $context['route'] = $this->routeContext($trip);
            $context['stops'] = $this->stopsContext($trip);
            $context['has_route'] = is_array($context['route'] ?? null)
                && ($context['route']['distance_km'] ?? null) !== null;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function roadProfileContext(Trip $trip): ?array
    {
        $profile = Trip::coerceStructuredArray($trip->getAttribute('road_profile'));

        if (! is_array($profile) || $profile === []) {
            return null;
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeContext(Trip $trip): ?array
    {
        $route = Trip::normalizeRoute(
            Trip::coerceStructuredArray($trip->getAttribute('route')),
        );

        if (! is_array($route)) {
            return null;
        }

        return [
            'distance_km' => $route['distance_km'] ?? null,
            'duration_seconds' => $route['duration_seconds'] ?? null,
            'has_tolls' => (bool) ($route['has_tolls'] ?? false),
        ];
    }

    /**
     * @return list<array{label: string, kind?: string|null}>
     */
    private function stopsContext(Trip $trip): array
    {
        $stops = Trip::coerceStructuredArray($trip->getAttribute('stops'));

        if (! is_array($stops)) {
            return [];
        }

        $normalized = [];

        foreach ($stops as $stop) {
            if (! is_array($stop)) {
                continue;
            }

            $label = trim((string) ($stop['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'kind' => isset($stop['kind']) ? (string) $stop['kind'] : null,
            ];
        }

        return $normalized;
    }

    private function dayCount(Trip $trip): int
    {
        if ($trip->start_date !== null && $trip->end_date !== null) {
            return max(1, (int) $trip->start_date->diffInDays($trip->end_date) + 1);
        }

        $itinerary = Trip::coerceStructuredArray($trip->getAttribute('itinerary')) ?? [];
        $days = is_array($itinerary['days'] ?? null) ? $itinerary['days'] : [];

        if ($days !== []) {
            return count($days);
        }

        return 5;
    }
}
