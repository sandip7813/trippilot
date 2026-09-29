<?php

namespace App\Actions\Trips;

use App\Enums\TripVisibility;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Toggles a trip's public visibility. Any user may publish their own trip as
 * an open trip, capped by config('trippilot.open_trips.max_active_per_user')
 * active (published and not yet past) trips at a time.
 */
class PublishOpenTrip
{
    public function publish(Trip $trip, User $owner): void
    {
        if ($trip->isPublic()) {
            return;
        }

        $missing = $trip->missingRequiredOpenTripFields();

        if ($missing !== []) {
            throw new RuntimeException(
                'Fill in these group details before publishing: '.implode(', ', $missing).'.'
            );
        }

        $limit = (int) config('trippilot.open_trips.max_active_per_user', 3);
        $active = $this->activePublicTripCount($owner, excluding: $trip);

        if ($active >= $limit) {
            throw new RuntimeException(
                "You can only have {$limit} active open trips at a time. Unpublish one first."
            );
        }

        $trip->update([
            'visibility' => TripVisibility::Public,
            'published_at' => Carbon::now(),
        ]);
    }

    public function unpublish(Trip $trip): void
    {
        $trip->update(['visibility' => TripVisibility::Private]);
    }

    private function activePublicTripCount(User $owner, Trip $excluding): int
    {
        return Trip::query()
            ->where('user_id', $owner->id)
            ->where('visibility', TripVisibility::Public->value)
            ->where('id', '!=', (string) $excluding->id)
            ->get()
            ->reject(fn (Trip $trip): bool => $trip->isPastTrip())
            ->count();
    }
}
