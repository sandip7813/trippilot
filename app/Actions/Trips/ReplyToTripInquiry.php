<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Models\TripInquiry;
use App\Models\User;
use App\Notifications\TripInquiryReplyNotification;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Either party in a thread (the owner or the original sender) can reply.
 * A reply always notifies the other participant.
 */
class ReplyToTripInquiry
{
    public function __invoke(Trip $trip, TripInquiry $inquiry, User $author, string $body): void
    {
        $isOwner = $trip->isOwnedBy($author);
        $isSender = $inquiry->involves($author);

        if (! $isOwner && ! $isSender) {
            throw new RuntimeException('You are not part of this conversation.');
        }

        $inquiry->update(['messages' => [...$inquiry->messageList(), [
            'from_user_id' => $author->id,
            'body' => $body,
            'created_at' => Carbon::now()->toIso8601String(),
        ]]]);

        $recipient = $isOwner
            ? User::query()->find((int) $inquiry->sender_id)
            : User::query()->find((int) $trip->user_id);

        $recipient?->notify(TripInquiryReplyNotification::forInquiry($trip, $inquiry));
    }
}
