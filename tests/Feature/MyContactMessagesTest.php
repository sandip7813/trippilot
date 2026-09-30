<?php

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\User;
use App\Notifications\ContactMessageRepliedNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('guests are sent to log in', function () {
    $this->get(route('contact.messages.index'))->assertRedirect(route('login'));
});

test('users see only their own contact messages', function () {
    $user = User::factory()->create();
    ContactMessage::factory()->fromUser($user)->create(['subject' => 'My question']);
    ContactMessage::factory()->fromUser(User::factory()->create())->create(['subject' => 'Someone else']);
    ContactMessage::factory()->create(['subject' => 'Guest message']);

    $this->actingAs($user)
        ->get(route('contact.messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContactMessages/Index')
            ->has('messages.data', 1)
            ->where('messages.data.0.subject', 'My question')
            ->where('messages.data.0.status_label', 'Awaiting reply'));
});

test('users can read the replies to their message', function () {
    $user = User::factory()->create();
    $contactMessage = ContactMessage::factory()->fromUser($user)->replied()->create();
    ContactMessageReply::factory()->for($contactMessage)->create(['body' => 'Here is your answer.']);

    $this->actingAs($user)
        ->get(route('contact.messages.show', $contactMessage))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContactMessages/Show')
            ->where('contactMessage.status_label', 'Answered')
            ->has('contactMessage.replies', 1)
            ->where('contactMessage.replies.0.body', 'Here is your answer.')
            ->missing('contactMessage.replies.0.author_name'));
});

test('users cannot read someone else\'s message', function () {
    $contactMessage = ContactMessage::factory()->fromUser(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('contact.messages.show', $contactMessage))
        ->assertForbidden();
});

test('the contact page tells signed in users how many messages they have sent', function () {
    $user = User::factory()->create();
    ContactMessage::factory()->fromUser($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('contact'))
        ->assertInertia(fn ($page) => $page->where('sender.messages_count', 2));
});

test('the sender is notified in the app when the team replies', function () {
    Mail::fake();
    Notification::fake();
    $user = User::factory()->create();
    $contactMessage = ContactMessage::factory()->fromUser($user)->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.contact-messages.reply', $contactMessage), [
            'body' => 'Thanks, all sorted!',
        ]);

    Notification::assertSentTo(
        $user,
        ContactMessageRepliedNotification::class,
        fn (ContactMessageRepliedNotification $notification) => $notification->contactMessageId === $contactMessage->id,
    );
});

test('guest senders get the reply by email only', function () {
    Mail::fake();
    Notification::fake();
    $contactMessage = ContactMessage::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.contact-messages.reply', $contactMessage), [
            'body' => 'Thanks for writing in.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});
