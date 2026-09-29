<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\TripExpenseEntry;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        return $trip->isViewableBy($user) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $trip->isEditableBy($user);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip) || $user->isAdmin();
    }

    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function generateItinerary(User $user, Trip $trip): bool
    {
        return $trip->isEditableBy($user);
    }

    public function chat(User $user, Trip $trip): bool
    {
        return $trip->isEditableBy($user);
    }

    /**
     * The expense sheet is private to the owner by default. It only opens up
     * to other collaborators once the owner sets it to shared.
     */
    public function viewExpenses(User $user, Trip $trip): bool
    {
        return $trip->isExpenseSheetSharedWith($user) || $user->isAdmin();
    }

    public function manageExpenses(User $user, Trip $trip): bool
    {
        return $trip->isEditableBy($user) && $trip->isExpenseSheetSharedWith($user);
    }

    /**
     * Editors manage the whole sheet; members may only add entries (their
     * own). Both require the sheet to be shared.
     */
    public function createExpenseEntry(User $user, Trip $trip): bool
    {
        if (! $trip->isExpenseSheetSharedWith($user)) {
            return false;
        }

        return $trip->isEditableBy($user) || $trip->isMember($user);
    }

    /**
     * Editors may change any entry; members only their own.
     */
    public function manageExpenseEntry(User $user, Trip $trip, TripExpenseEntry $entry): bool
    {
        if (! $trip->isExpenseSheetSharedWith($user)) {
            return false;
        }

        if ($trip->isEditableBy($user)) {
            return true;
        }

        return $trip->isMember($user) && (int) ($entry->created_by['user_id'] ?? 0) === $user->id;
    }

    public function manageCollaborators(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
    }

    /**
     * Members may leave on their own; the owner removes anyone.
     */
    public function leaveTrip(User $user, Trip $trip): bool
    {
        return $trip->isCollaborator($user);
    }

    /**
     * Only the owner can flip a trip's visibility or edit its open-trip
     * details. Publishing further requires the owner to be under their
     * active-public-trip limit, which the action layer enforces.
     */
    public function publish(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
    }

    /**
     * Anyone, including guests (pass null), can view a public trip's
     * read-only overview. Callers must still build the response from the
     * whitelisted public data shape, never the owner's full payload.
     */
    public function viewPublic(?User $user, Trip $trip): bool
    {
        return $trip->isPublic();
    }

    /**
     * Any logged-in user, other than the owner, may contact the owner of a
     * public trip.
     */
    public function contact(User $user, Trip $trip): bool
    {
        return $trip->isPublic() && ! $this->ownsTrip($user, $trip);
    }

    public function manageInquiries(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
    }

    /**
     * Any logged-in user, other than the owner, may request to join a
     * joinable trip. Seat/deadline/past checks live on Trip::isJoinable().
     */
    public function requestToJoin(User $user, Trip $trip): bool
    {
        return $trip->isJoinable() && ! $this->ownsTrip($user, $trip);
    }

    public function manageJoinRequests(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
    }

    /**
     * Any logged-in user, other than the owner, may report a public trip.
     */
    public function report(User $user, Trip $trip): bool
    {
        return $trip->isPublic() && ! $this->ownsTrip($user, $trip);
    }

    protected function ownsTrip(User $user, Trip $trip): bool
    {
        return (int) $trip->user_id === $user->id;
    }
}
