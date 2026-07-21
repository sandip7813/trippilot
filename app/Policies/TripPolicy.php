<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
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
        return $this->ownsTrip($user, $trip);
    }

    public function chat(User $user, Trip $trip): bool
    {
        return $this->ownsTrip($user, $trip);
    }

    protected function ownsTrip(User $user, Trip $trip): bool
    {
        return (int) $trip->user_id === $user->id;
    }
}
