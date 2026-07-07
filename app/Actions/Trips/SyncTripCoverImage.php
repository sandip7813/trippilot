<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Services\Trips\TripCoverImageService;

class SyncTripCoverImage
{
    public function __construct(private TripCoverImageService $coverImageService) {}

    public function __invoke(Trip $trip): bool
    {
        return $this->coverImageService->generateForTrip($trip) !== null;
    }
}
