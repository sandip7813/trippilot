<?php

namespace App\Notifications;

use App\Enums\TripJoinRequestStatus;
use App\Models\Trip;
use Illuminate\Notifications\Notification;

class TripJoinRequestDecidedNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public TripJoinRequestStatus $status,
    ) {}

    public static function forTrip(Trip $trip, TripJoinRequestStatus $status): self
    {
        return new self($trip->id, $trip->title, $status);
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
        $verb = $this->status === TripJoinRequestStatus::Accepted ? 'accepted' : 'declined';

        return [
            'trip_id' => $this->tripId,
            'title' => "Your request to join \"{$this->tripTitle}\" was {$verb}",
            'message' => $this->status === TripJoinRequestStatus::Accepted
                ? "You're in! You now have access to this trip."
                : 'The organizer declined your join request.',
            'url' => $this->status === TripJoinRequestStatus::Accepted
                ? route('trips.show', $this->tripId, absolute: false)
                : route('open-trips.show', $this->tripId, absolute: false),
        ];
    }
}
