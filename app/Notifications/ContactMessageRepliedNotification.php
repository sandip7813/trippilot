<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a signed-up sender the team has replied to their contact message.
 * The reply itself goes out by email; this links to the thread in the app.
 */
class ContactMessageRepliedNotification extends Notification
{
    public function __construct(
        public int $contactMessageId,
        public string $subject,
    ) {}

    public static function forMessage(ContactMessage $contactMessage): self
    {
        return new self($contactMessage->id, $contactMessage->subject);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{contact_message_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_message_id' => $this->contactMessageId,
            'title' => 'The TripPilot team replied',
            'message' => 'Re: '.Str::limit($this->subject, 80),
            'url' => route('contact.messages.show', $this->contactMessageId, absolute: false),
        ];
    }
}
