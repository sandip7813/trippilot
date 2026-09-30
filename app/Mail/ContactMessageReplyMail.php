<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contactMessage,
        public ContactMessageReply $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Re: {$this->contactMessage->subject} [{$this->contactMessage->reference()}]",
        );
    }

    public function content(): Content
    {
        $isRegistered = $this->contactMessage->user_id !== null;

        return new Content(
            markdown: 'mail.contact.reply',
            with: [
                'name' => $this->contactMessage->name,
                'reference' => $this->contactMessage->reference(),
                'replyBody' => $this->reply->body,
                'repliedAt' => $this->reply->created_at?->format(ContactMessage::EMAIL_DATE_FORMAT),
                'topic' => $this->contactMessage->topic->label(),
                'originalSubject' => $this->contactMessage->subject,
                'originalMessage' => $this->contactMessage->message,
                'sentAt' => $this->contactMessage->created_at?->format(ContactMessage::EMAIL_DATE_FORMAT),
                'actionUrl' => $isRegistered
                    ? route('contact.messages.show', $this->contactMessage)
                    : route('contact'),
                'actionLabel' => $isRegistered ? 'View the conversation' : 'Contact us again',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
