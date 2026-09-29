<?php

namespace App\Actions\Trips;

use App\Enums\TripReportStatus;
use App\Models\Trip;
use App\Models\TripReport;
use App\Models\User;

class SubmitTripReport
{
    public function __invoke(Trip $trip, User $reporter, string $reason, ?string $message): TripReport
    {
        return TripReport::query()->create([
            'trip_id' => (string) $trip->id,
            'reporter_id' => $reporter->id,
            'reason' => $reason,
            'message' => $message,
            'status' => TripReportStatus::Open,
        ]);
    }
}
