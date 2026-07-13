<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\TripGenerator;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;

class GenerateTripItinerary
{
    public function __construct(
        private TripGenerator $tripGenerator,
        private SyncTripCoverImage $syncTripCoverImage,
        private TripRouteResolver $routeResolver,
    ) {}

    public function __invoke(Trip $trip): Trip
    {
        $generated = $this->tripGenerator->generate('', $this->buildContext($trip));

        $trip->update([
            'itinerary' => $generated->toTripItinerary(),
            'status' => TripStatus::Planned,
        ]);

        $trip = $trip->fresh();

        ($this->syncTripCoverImage)($trip, onlyIfMissing: true);

        return $trip;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Trip $trip): array
    {
        return [
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
        ];
    }

    private function dayCount(Trip $trip): int
    {
        if ($trip->start_date !== null && $trip->end_date !== null) {
            return max(1, (int) $trip->start_date->diffInDays($trip->end_date) + 1);
        }

        return 5;
    }
}
