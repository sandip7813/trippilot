<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Models\TripInquiry;
use Illuminate\Notifications\Notification;

/**
 * Sent to the original sender when the owner replies to their inquiry.
 */
class TripInquiryReplyNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public string $inquiryId,
    ) {}

    public static function forInquiry(Trip $trip, TripInquiry $inquiry): self
    {
        return new self($trip->id, $trip->title, $inquiry->id);
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
            'title' => "New reply about \"{$this->tripTitle}\"",
            'message' => 'The organizer replied to your message.',
            'url' => route('open-trips.show', $this->tripId, absolute: false),
        ];
    }
}
