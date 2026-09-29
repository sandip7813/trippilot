<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripMembershipEndedNotification;
use RuntimeException;

/**
 * Self-service departure for an accepted open-trip member. Owner-initiated
 * removal goes through RemoveTripCollaborator instead, which already
 * handles the expense-participant side effect for any role.
 */
class LeaveOpenTrip
{
    public function __construct(
        private readonly RemoveTripCollaborator $removeTripCollaborator,
    ) {}

    public function __invoke(Trip $trip, User $member): void
    {
        if (! $trip->isMember($member)) {
            throw new RuntimeException('You are not a member of this trip.');
        }

        ($this->removeTripCollaborator)($trip, $member->email);

        $owner = User::query()->find((int) $trip->user_id);
        $owner?->notify(TripMembershipEndedNotification::memberLeft($trip, $member->first_name));
    }
}
