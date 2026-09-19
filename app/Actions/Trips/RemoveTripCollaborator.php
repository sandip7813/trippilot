<?php

namespace App\Actions\Trips;

use App\Models\Trip;

class RemoveTripCollaborator
{
    public function __invoke(Trip $trip, string $email): void
    {
        $entries = collect($trip->collaboratorEntries())
            ->reject(fn (array $entry): bool => strcasecmp((string) $entry['email'], $email) === 0)
            ->values()
            ->all();

        $trip->update(['collaborators' => $entries]);
    }
}
