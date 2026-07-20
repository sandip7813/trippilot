<?php

namespace App\Actions\Trips;

use App\Contracts\Ai\TripGenerator;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trips\TripAiContextBuilder;

class GenerateTripItinerary
{
    public function __construct(
        private TripGenerator $tripGenerator,
        private SyncTripCoverImage $syncTripCoverImage,
        private TripAiContextBuilder $contextBuilder,
    ) {}

    public function __invoke(Trip $trip): Trip
    {
        $generated = $this->tripGenerator->generate('', $this->contextBuilder->build($trip));

        $trip->update([
            'itinerary' => $generated->toTripItinerary(),
            'status' => TripStatus::Planned,
        ]);

        $trip = $trip->fresh();

        ($this->syncTripCoverImage)($trip, onlyIfMissing: true);

        return $trip;
    }
}
