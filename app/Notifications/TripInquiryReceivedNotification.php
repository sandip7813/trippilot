<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Models\TripInquiry;
use Illuminate\Notifications\Notification;

/**
 * Sent to the trip owner when someone sends a new contact inquiry. No email
 * addresses are included; it only links back to the portal.
 */
class TripInquiryReceivedNotification extends Notification
{
    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public string $inquiryId,
        public string $senderName,
    ) {}

    public static function forInquiry(Trip $trip, TripInquiry $inquiry, string $senderName): self
    {
        return new self($trip->id, $trip->title, $inquiry->id, $senderName);
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
            'title' => "New inquiry about \"{$this->tripTitle}\"",
            'message' => "{$this->senderName} sent you a message.",
            'url' => route('trips.inquiries.index', $this->tripId, absolute: false),
        ];
    }
}
