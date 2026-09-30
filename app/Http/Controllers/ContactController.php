<?php

namespace App\Http\Controllers;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactTopic;
use App\Enums\UserRole;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use App\Rules\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public contact page, open to guests and signed-in users alike.
 */
class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Contact', [
            'topics' => ContactTopic::options(),
            'sender' => $user === null ? null : [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->mobile_number,
                'messages_count' => $user->contactMessages()->count(),
            ],
            'recaptcha' => [
                'enabled' => $user === null && Recaptcha::isActive(),
                'siteKey' => config('recaptcha.site_key'),
            ],
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $user = $request->user();

        $contactMessage = ContactMessage::create([
            'user_id' => $user?->id,
            'name' => $user->name ?? $request->validated('name'),
            'email' => $user->email ?? $request->validated('email'),
            'phone' => $request->validated('phone') ?: $user?->mobile_number,
            'topic' => $request->validated('topic'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => ContactMessageStatus::New,
        ]);

        Notification::send(
            User::query()->where('role', UserRole::SuperAdmin->value)->get(),
            ContactMessageReceivedNotification::forMessage($contactMessage),
        );

        Inertia::flash('contactReference', $contactMessage->reference());

        return to_route('contact');
    }
}
