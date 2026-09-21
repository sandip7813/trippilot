<?php

namespace App\Mail;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripExpenseSheetReopenedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public string $participantId,
        public string $reopenedByName,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Settled expenses reopened for \"{$this->trip->title}\"",
        );
    }

    public function content(): Content
    {
        $participant = $this->trip->expenseSheet()?->findParticipant($this->participantId);

        return new Content(
            markdown: 'mail.expenses.reopened',
            with: [
                'participantName' => $participant['name'] ?? 'there',
                'tripTitle' => $this->trip->title,
                'reopenedBy' => $this->reopenedByName,
                'reason' => $this->reason,
                'tripUrl' => ($participant['user_id'] ?? null) !== null
                    ? ($this->trip->isRoadTrip() ? route('road-trips.show', $this->trip) : route('trips.show', $this->trip))
                    : null,
            ],
        );
    }
}
