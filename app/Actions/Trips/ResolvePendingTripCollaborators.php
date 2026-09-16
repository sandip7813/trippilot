<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Models\User;

/**
 * Links a newly registered user to any trips that invited their email
 * before they had an account.
 */
class ResolvePendingTripCollaborators
{
    public function __invoke(User $user): void
    {
        Trip::query()
            ->where('collaborators.email', strtolower($user->email))
            ->get()
            ->each(function (Trip $trip) use ($user): void {
                $entries = collect($trip->collaboratorEntries())
                    ->map(function (array $entry) use ($user): array {
                        if ($entry['status'] === 'pending' && strcasecmp((string) $entry['email'], $user->email) === 0) {
                            $entry['user_id'] = $user->id;
                            $entry['status'] = 'accepted';
                        }

                        return $entry;
                    })
                    ->values()
                    ->all();

                $trip->update(['collaborators' => $entries]);
            });
    }
}
