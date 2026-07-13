<?php

namespace App\Actions\Trips;

use App\Jobs\SyncTripCoverImageJob;
use App\Models\Trip;
use Illuminate\Support\Str;

class SyncTripCoverImage
{
    public function __invoke(Trip $trip, bool $onlyIfMissing = false): void
    {
        $regenerationToken = $onlyIfMissing ? null : (string) Str::uuid();
        $tryNextSource = ! $onlyIfMissing && filled($trip->cover_image_path);

        SyncTripCoverImageJob::dispatch(
            (string) $trip->id,
            $onlyIfMissing,
            $regenerationToken,
            tryNextSource: $tryNextSource,
        );
    }
}
