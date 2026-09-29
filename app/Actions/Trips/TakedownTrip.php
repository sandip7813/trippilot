<?php

namespace App\Actions\Trips;

use App\Enums\TripReportStatus;
use App\Enums\TripVisibility;
use App\Models\Trip;
use App\Models\TripReport;
use App\Models\User;
use App\Notifications\TripTakenDownNotification;

/**
 * Admin moderation: unpublishes a trip and marks every open report against
 * it as actioned.
 */
class TakedownTrip
{
    public function __invoke(Trip $trip): void
    {
        $trip->update(['visibility' => TripVisibility::Private]);

        TripReport::query()
            ->where('trip_id', (string) $trip->id)
            ->where('status', TripReportStatus::Open->value)
            ->get()
            ->each(fn (TripReport $report) => $report->update(['status' => TripReportStatus::ActionTaken]));

        User::query()->find((int) $trip->user_id)?->notify(TripTakenDownNotification::forTrip($trip));
    }
}
