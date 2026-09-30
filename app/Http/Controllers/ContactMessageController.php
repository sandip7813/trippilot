<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets signed-in users look back at what they sent through the contact page.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $messages = $request->user()
            ->contactMessages()
            ->withCount('replies')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ContactMessage $contactMessage): array => [
                ...$this->summary($contactMessage),
                'excerpt' => Str::limit($contactMessage->message, 160),
                'replies_count' => $contactMessage->replies_count,
            ]);

        return Inertia::render('ContactMessages/Index', [
            'messages' => $messages,
        ]);
    }

    public function show(ContactMessage $contactMessage): Response
    {
        $this->authorize('view', $contactMessage);

        $contactMessage->load('replies');

        return Inertia::render('ContactMessages/Show', [
            'contactMessage' => [
                ...$this->summary($contactMessage),
                'message' => $contactMessage->message,
                'replies' => $contactMessage->replies->map(fn (ContactMessageReply $reply): array => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'created_at' => $reply->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ContactMessage $contactMessage): array
    {
        return [
            'id' => $contactMessage->id,
            'reference' => $contactMessage->reference(),
            'topic_label' => $contactMessage->topic->label(),
            'subject' => $contactMessage->subject,
            'status' => $contactMessage->status->value,
            'status_label' => $this->statusLabel($contactMessage->status),
            'created_at' => $contactMessage->created_at?->toIso8601String(),
            'replied_at' => $contactMessage->replied_at?->toIso8601String(),
        ];
    }

    /**
     * Status wording from the sender's point of view.
     */
    private function statusLabel(ContactMessageStatus $status): string
    {
        return match ($status) {
            ContactMessageStatus::New => 'Awaiting reply',
            ContactMessageStatus::Replied => 'Answered',
            ContactMessageStatus::Closed => 'Closed',
        };
    }
}
