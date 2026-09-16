<?php

namespace App\Actions\Trips;

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;

class UpdateTripCollaboratorRole
{
    public function __invoke(Trip $trip, string $email, TripCollaboratorRole $role): void
    {
        $entries = collect($trip->collaboratorEntries())
            ->map(function (array $entry) use ($email, $role): array {
                if (strcasecmp((string) $entry['email'], $email) === 0) {
                    $entry['role'] = $role->value;
                }

                return $entry;
            })
            ->values()
            ->all();

        $trip->update(['collaborators' => $entries]);
    }
}
