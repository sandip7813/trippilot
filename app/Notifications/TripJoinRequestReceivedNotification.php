<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Notification;

class TripJoinRequestReceivedNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public string $requesterName,
    ) {}

    public static function forTrip(Trip $trip, string $requesterName): self
    {
        return new self($trip->id, $trip->title, $requesterName);
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
            'title' => "New join request for \"{$this->tripTitle}\"",
            'message' => "{$this->requesterName} wants to join this trip.",
            'url' => route('trips.join-requests.index', $this->tripId, absolute: false),
        ];
    }
}
