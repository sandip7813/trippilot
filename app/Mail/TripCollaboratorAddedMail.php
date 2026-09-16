<?php

namespace App\Mail;

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripCollaboratorAddedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public TripCollaboratorRole $role,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been added to \"{$this->trip->title}\" on TripPilot",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trips.collaborator-added',
            with: [
                'tripTitle' => $this->trip->title,
                'roleLabel' => $this->role->label(),
                'tripUrl' => route('trips.show', $this->trip),
            ],
        );
    }
}
