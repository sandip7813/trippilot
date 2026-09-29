<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner when a member leaves on their own, or to the member
 * when the owner removes them.
 */
class TripMembershipEndedNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public string $message,
    ) {}

    public static function memberLeft(Trip $trip, string $memberName): self
    {
        return new self($trip->id, $trip->title, "{$memberName} left \"{$trip->title}\".");
    }

    public static function removedByOwner(Trip $trip): self
    {
        return new self($trip->id, $trip->title, "You were removed from \"{$trip->title}\".");
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{trip_id: string, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'trip_id' => $this->tripId,
            'title' => $this->tripTitle,
            'message' => $this->message,
            'url' => route('open-trips.show', $this->tripId, absolute: false),
        ];
    }
}
