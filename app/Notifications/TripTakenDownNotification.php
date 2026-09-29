<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Notification;

class TripTakenDownNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
    ) {}

    public static function forTrip(Trip $trip): self
    {
        return new self($trip->id, $trip->title);
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
            'title' => "\"{$this->tripTitle}\" was unpublished",
            'message' => 'An admin took this trip down after a review. It is now private.',
            'url' => route('trips.show', $this->tripId, absolute: false),
        ];
    }
}
