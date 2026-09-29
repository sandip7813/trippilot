<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Models\TripInquiry;
use App\Models\User;
use App\Notifications\TripInquiryReceivedNotification;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Starts (or continues, if one already exists) a single contact thread
 * between a sender and the trip owner. Kept entirely in-platform.
 */
class SendTripInquiry
{
    public function __invoke(Trip $trip, User $sender, string $subject, string $body): TripInquiry
    {
        if ($trip->isOwnedBy($sender)) {
            throw new RuntimeException('You cannot send an inquiry on your own trip.');
        }

        $inquiry = TripInquiry::query()
            ->where('trip_id', (string) $trip->id)
            ->where('sender_id', $sender->id)
            ->first();

        $message = [
            'from_user_id' => $sender->id,
            'body' => $body,
            'created_at' => Carbon::now()->toIso8601String(),
        ];

        if ($inquiry === null) {
            $inquiry = TripInquiry::query()->create([
                'trip_id' => (string) $trip->id,
                'sender_id' => $sender->id,
                'subject' => $subject,
                'messages' => [$message],
            ]);
        } else {
            $inquiry->update(['messages' => [...$inquiry->messageList(), $message]]);
        }

        $owner = User::query()->find((int) $trip->user_id);

        if ($owner !== null) {
            $owner->notify(TripInquiryReceivedNotification::forInquiry($trip, $inquiry, $sender->first_name));
        }

        return $inquiry;
    }
}
