<?php

namespace App\Notifications;

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use Illuminate\Notifications\Notification;

/**
 * In-app companion to TripCollaboratorAddedMail (which already covers email).
 */
class TripSharedNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public TripCollaboratorRole $role,
    ) {}

    public static function forTrip(Trip $trip, TripCollaboratorRole $role): self
    {
        return new self($trip->id, $trip->title, $role);
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
            'title' => "You were added to \"{$this->tripTitle}\"",
            'message' => "You now have {$this->role->label()} access to this trip.",
            'url' => route('trips.show', $this->tripId, absolute: false),
        ];
    }
}
