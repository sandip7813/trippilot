<?php

namespace App\Actions\Trips;

use App\Enums\TripJoinRequestStatus;
use App\Models\Trip;
use App\Models\TripJoinRequest;
use App\Models\User;
use App\Notifications\TripJoinRequestReceivedNotification;
use RuntimeException;

class SubmitTripJoinRequest
{
    /**
     * @param  array{travelers_count: int, phone: string, message?: string|null}  $data
     */
    public function __invoke(Trip $trip, User $user, array $data): TripJoinRequest
    {
        if ($trip->isOwnedBy($user)) {
            throw new RuntimeException('You cannot request to join your own trip.');
        }

        if (! $trip->isJoinable()) {
            throw new RuntimeException('This trip is no longer accepting join requests.');
        }

        $existing = TripJoinRequest::query()
            ->where('trip_id', (string) $trip->id)
            ->where('user_id', $user->id)
            ->where('status', TripJoinRequestStatus::Pending->value)
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('You already have a pending request for this trip.');
        }

        $request = TripJoinRequest::query()->create([
            'trip_id' => (string) $trip->id,
            'user_id' => $user->id,
            'travelers_count' => $data['travelers_count'],
            'phone' => $data['phone'],
            'message' => $data['message'] ?? null,
            'status' => TripJoinRequestStatus::Pending,
        ]);

        $owner = User::query()->find((int) $trip->user_id);
        $owner?->notify(TripJoinRequestReceivedNotification::forTrip($trip, $user->first_name));

        return $request;
    }
}
