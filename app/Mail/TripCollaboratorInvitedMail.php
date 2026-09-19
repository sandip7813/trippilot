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

class TripCollaboratorInvitedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trip $trip,
        public TripCollaboratorRole $role,
        public string $invitedEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to plan \"{$this->trip->title}\" on TripPilot",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trips.collaborator-invited',
            with: [
                'tripTitle' => $this->trip->title,
                'roleLabel' => $this->role->label(),
                'invitedEmail' => $this->invitedEmail,
                'registerUrl' => route('register'),
            ],
        );
    }
}
