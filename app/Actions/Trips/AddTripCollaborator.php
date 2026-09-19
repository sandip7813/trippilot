<?php

namespace App\Actions\Trips;

use App\Enums\TripCollaboratorRole;
use App\Mail\TripCollaboratorAddedMail;
use App\Mail\TripCollaboratorInvitedMail;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripSharedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AddTripCollaborator
{
    public function __invoke(Trip $trip, string $email, TripCollaboratorRole $role): void
    {
        $email = strtolower($email);
        $collaborator = User::query()->where('email', $email)->first();

        if ($collaborator !== null && $trip->isOwnedBy($collaborator)) {
            throw new RuntimeException('The trip owner cannot be added as a collaborator.');
        }

        $entries = collect($trip->collaboratorEntries())
            ->reject(fn (array $entry): bool => strcasecmp((string) $entry['email'], $email) === 0)
            ->push([
                'user_id' => $collaborator?->id,
                'email' => $email,
                'role' => $role->value,
                'status' => $collaborator !== null ? 'accepted' : 'pending',
                'added_at' => Carbon::now()->toIso8601String(),
            ])
            ->values()
            ->all();

        $trip->update(['collaborators' => $entries]);

        if ($collaborator !== null) {
            Mail::to($collaborator)->queue(new TripCollaboratorAddedMail($trip, $role));
            $collaborator->notify(TripSharedNotification::forTrip($trip, $role));
        } else {
            Mail::to($email)->queue(new TripCollaboratorInvitedMail($trip, $role, $email));
        }
    }
}
