<?php

namespace App\Actions\Trips;

use App\Jobs\SyncTripCoverImageJob;
use App\Models\Trip;

class SyncTripCoverImage
{
    public function __invoke(Trip $trip, bool $onlyIfMissing = false): void
    {
        SyncTripCoverImageJob::dispatch((string) $trip->id, $onlyIfMissing);
    }
}
