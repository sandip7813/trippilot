<?php

namespace App\Actions\Trips;

use App\Enums\TripJoinRequestStatus;
use App\Models\TripJoinRequest;
use App\Models\User;
use RuntimeException;

class WithdrawTripJoinRequest
{
    public function __invoke(TripJoinRequest $request, User $user): void
    {
        if ((int) $request->user_id !== $user->id) {
            throw new RuntimeException('This is not your request.');
        }

        if (! $request->isPending()) {
            throw new RuntimeException('This request has already been decided.');
        }

        $request->update(['status' => TripJoinRequestStatus::Withdrawn]);
    }
}
