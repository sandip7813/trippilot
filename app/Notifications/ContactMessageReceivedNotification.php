<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells super admins a new message arrived through the contact page, both in
 * the app and by email. Queued so a slow mail server never delays the sender.
 */
class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage) {}

    public static function forMessage(ContactMessage $contactMessage): self
    {
        return new self($contactMessage);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contactMessage = $this->contactMessage;

        return (new MailMessage)
            ->subject("New contact message: {$contactMessage->subject} [{$contactMessage->reference()}]")
            ->markdown('mail.contact.received', [
                'reference' => $contactMessage->reference(),
                'name' => $contactMessage->name,
                'email' => $contactMessage->email,
                'phone' => $contactMessage->phone,
                'isRegistered' => $contactMessage->user_id !== null,
                'topic' => $contactMessage->topic->label(),
                'subject' => $contactMessage->subject,
                'body' => $contactMessage->message,
                'receivedAt' => $contactMessage->created_at?->format(ContactMessage::EMAIL_DATE_FORMAT),
                'inboxUrl' => route('admin.super.contact-messages.show', $contactMessage),
            ]);
    }

    /**
     * @return array{contact_message_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_message_id' => $this->contactMessage->id,
            'title' => 'New contact message',
            'message' => "{$this->contactMessage->name}: ".Str::limit($this->contactMessage->subject, 80),
            'url' => route('admin.super.contact-messages.show', $this->contactMessage, absolute: false),
        ];
    }
}
