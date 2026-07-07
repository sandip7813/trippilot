<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\TripGenerator;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trips\TripCoverImageService;

class GenerateTripItinerary
{
    public function __construct(
        private TripGenerator $tripGenerator,
        private TripCoverImageService $coverImageService,
    ) {}

    public function __invoke(Trip $trip): Trip
    {
        $generated = $this->tripGenerator->generate('', $this->buildContext($trip));

        $trip->update([
            'itinerary' => $generated->toTripItinerary(),
            'status' => TripStatus::Planned,
        ]);

        $trip = $trip->fresh();

        $this->coverImageService->generateForTripIfMissing($trip);

        return $trip->fresh();
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
            return max(1, $trip->start_date->diffInDays($trip->end_date) + 1);
        }

        return 5;
    }
}
