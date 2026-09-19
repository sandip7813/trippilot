<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TripReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tripId,
        public string $tripTitle,
        public int $daysUntil,
    ) {}

    public static function forTrip(Trip $trip, int $daysUntil): self
    {
        return new self($trip->id, $trip->title, $daysUntil);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->headline())
            ->greeting('Get ready for your trip!')
            ->line($this->message())
            ->action('View trip', route('trips.show', $this->tripId));
    }

    /**
     * @return array{trip_id: string, title: string, message: string, url: string, days_until: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'trip_id' => $this->tripId,
            'title' => $this->headline(),
            'message' => $this->message(),
            'url' => route('trips.show', $this->tripId, absolute: false),
            'days_until' => $this->daysUntil,
        ];
    }

    private function headline(): string
    {
        return match ($this->daysUntil) {
            0 => "\"{$this->tripTitle}\" starts today",
            1 => "\"{$this->tripTitle}\" starts tomorrow",
            default => "\"{$this->tripTitle}\" starts in {$this->daysUntil} days",
        };
    }

    private function message(): string
    {
        return "Your trip \"{$this->tripTitle}\" is coming up. Review your itinerary, packing list and bookings.";
    }
}
