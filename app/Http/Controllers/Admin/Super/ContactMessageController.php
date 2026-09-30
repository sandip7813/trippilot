<?php

namespace App\Http\Controllers\Admin\Super;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyContactMessageRequest;
use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Notifications\ContactMessageRepliedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Super admin inbox for messages sent through the public contact page.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $status = ContactMessageStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));

        $messages = ContactMessage::query()
            ->withCount('replies')
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContactMessage $contactMessage): array => [
                ...$this->summary($contactMessage),
                'excerpt' => Str::limit($contactMessage->message, 140),
                'replies_count' => $contactMessage->replies_count,
            ]);

        $counts = ContactMessage::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('admin/super/contact-messages/Index', [
            'messages' => $messages,
            'filters' => [
                'status' => $status?->value,
                'search' => $search,
            ],
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect(ContactMessageStatus::cases())
                    ->mapWithKeys(fn (ContactMessageStatus $case): array => [$case->value => (int) ($counts[$case->value] ?? 0)])
                    ->all(),
            ],
        ]);
    }

    public function show(ContactMessage $contactMessage): Response
    {
        $contactMessage->load(['user', 'replies.author']);

        return Inertia::render('admin/super/contact-messages/Show', [
            'contactMessage' => [
                ...$this->summary($contactMessage),
                'message' => $contactMessage->message,
                'phone' => $contactMessage->phone,
                'is_registered' => $contactMessage->user !== null,
                'replies' => $contactMessage->replies->map(fn (ContactMessageReply $reply): array => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'author_name' => $reply->author->name ?? 'TripPilot team',
                    'created_at' => $reply->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Save the reply and email it to the sender. Nothing is saved if the
     * email cannot be sent, so the thread never shows an undelivered reply.
     */
    public function reply(ReplyContactMessageRequest $request, ContactMessage $contactMessage): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $contactMessage): void {
                $reply = $contactMessage->replies()->create([
                    'user_id' => $request->user()->id,
                    'body' => $request->validated('body'),
                ]);

                $contactMessage->update([
                    'status' => $request->boolean('close') ? ContactMessageStatus::Closed : ContactMessageStatus::Replied,
                    'replied_at' => now(),
                ]);

                Mail::to($contactMessage->email, $contactMessage->name)
                    ->send(new ContactMessageReplyMail($contactMessage, $reply));
            });
        } catch (Throwable $exception) {
            Log::error('Failed to send contact message reply.', [
                'contact_message_id' => $contactMessage->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->withErrors(['body' => __('The reply could not be emailed. Please try again.')]);
        }

        $contactMessage->user?->notify(ContactMessageRepliedNotification::forMessage($contactMessage));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply emailed to :email.', ['email' => $contactMessage->email])]);

        return back();
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContactMessageStatus::class)],
        ]);

        $contactMessage->update(['status' => $validated['status']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message marked as :status.', [
            'status' => strtolower($contactMessage->status->label()),
        ])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ContactMessage $contactMessage): array
    {
        return [
            'id' => $contactMessage->id,
            'reference' => $contactMessage->reference(),
            'name' => $contactMessage->name,
            'email' => $contactMessage->email,
            'topic' => $contactMessage->topic->value,
            'topic_label' => $contactMessage->topic->label(),
            'subject' => $contactMessage->subject,
            'status' => $contactMessage->status->value,
            'status_label' => $contactMessage->status->label(),
            'created_at' => $contactMessage->created_at?->toIso8601String(),
            'replied_at' => $contactMessage->replied_at?->toIso8601String(),
        ];
    }
}
