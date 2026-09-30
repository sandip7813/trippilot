<?php

use App\Enums\ContactMessageStatus;
use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('only super admins can open the contact inbox', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.super.contact-messages.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.super.contact-messages.index'))
        ->assertForbidden();
});

test('super admins can list and filter contact messages', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    ContactMessage::factory()->create(['subject' => 'Goa question']);
    ContactMessage::factory()->replied()->create(['subject' => 'Already answered']);

    $this->actingAs($superAdmin)
        ->get(route('admin.super.contact-messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/super/contact-messages/Index')
            ->has('messages.data', 2)
            ->where('counts.all', 2)
            ->where('counts.new', 1)
            ->where('counts.replied', 1));

    $this->actingAs($superAdmin)
        ->get(route('admin.super.contact-messages.index', ['status' => 'new']))
        ->assertInertia(fn ($page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.subject', 'Goa question'));

    $this->actingAs($superAdmin)
        ->get(route('admin.super.contact-messages.index', ['search' => 'answered']))
        ->assertInertia(fn ($page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.subject', 'Already answered'));
});

test('super admins can view a contact message with its replies', function () {
    $contactMessage = ContactMessage::factory()->replied()->create();
    ContactMessageReply::factory()->for($contactMessage)->create(['body' => 'Thanks for writing!']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.super.contact-messages.show', $contactMessage))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/super/contact-messages/Show')
            ->where('contactMessage.email', $contactMessage->email)
            ->has('contactMessage.replies', 1)
            ->where('contactMessage.replies.0.body', 'Thanks for writing!'));
});

test('super admins can reply and the reply is emailed to the sender', function () {
    Mail::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    $contactMessage = ContactMessage::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($superAdmin)
        ->post(route('admin.super.contact-messages.reply', $contactMessage), [
            'body' => 'Happy to help with your Goa trip!',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $contactMessage->refresh();

    expect($contactMessage->status)->toBe(ContactMessageStatus::Replied)
        ->and($contactMessage->replied_at)->not->toBeNull()
        ->and($contactMessage->replies()->sole())
        ->body->toBe('Happy to help with your Goa trip!')
        ->user_id->toBe($superAdmin->id);

    Mail::assertSent(ContactMessageReplyMail::class, fn (ContactMessageReplyMail $mail) => $mail->hasTo('jane@example.com')
        && $mail->reply->body === 'Happy to help with your Goa trip!');
});

test('the reply email lays out the reply, ticket details and original message', function () {
    $contactMessage = ContactMessage::factory()->create([
        'subject' => 'Goa question',
        'topic' => 'trip_planning',
        'message' => 'What is the best month to visit?',
    ]);
    $reply = ContactMessageReply::factory()->for($contactMessage)->create(['body' => 'November is lovely.']);

    $mail = new ContactMessageReplyMail($contactMessage, $reply);

    $mail->assertHasSubject("Re: Goa question [{$contactMessage->reference()}]");
    $mail->assertSeeInOrderInHtml([
        'Our reply',
        'November is lovely.',
        'Ticket number',
        $contactMessage->reference(),
        'Sent on',
        $contactMessage->created_at->format(ContactMessage::EMAIL_DATE_FORMAT),
        'Help planning a trip',
        'Goa question',
        'Your message',
        'What is the best month to visit?',
    ]);
});

test('messages in contact emails are shown exactly as typed', function () {
    $contactMessage = ContactMessage::factory()->create([
        'message' => "# Not a heading\n<b>not bold</b>",
    ]);
    $reply = ContactMessageReply::factory()->for($contactMessage)->create(['body' => 'Thanks!']);

    $html = (new ContactMessageReplyMail($contactMessage, $reply))->render();

    expect($html)->toContain('# Not a heading<br>&lt;b&gt;not bold&lt;/b&gt;')
        ->not->toContain('<h1>Not a heading');
});

test('registered senders get a link to the conversation, guests to the contact page', function () {
    $registered = ContactMessage::factory()->fromUser(User::factory()->create())->create();
    $guest = ContactMessage::factory()->create();

    (new ContactMessageReplyMail($registered, ContactMessageReply::factory()->for($registered)->create()))
        ->assertSeeInHtml(route('contact.messages.show', $registered));
    (new ContactMessageReplyMail($guest, ContactMessageReply::factory()->for($guest)->create()))
        ->assertSeeInHtml(route('contact'))
        ->assertDontSeeInHtml(route('contact.messages.show', $guest));
});

test('replying can close the conversation', function () {
    Mail::fake();
    $contactMessage = ContactMessage::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.contact-messages.reply', $contactMessage), [
            'body' => 'All sorted, closing this one.',
            'close' => true,
        ]);

    expect($contactMessage->refresh()->status)->toBe(ContactMessageStatus::Closed);
});

test('a reply is not saved when the email cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
    $contactMessage = ContactMessage::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.contact-messages.reply', $contactMessage), [
            'body' => 'This will not be delivered.',
        ])
        ->assertSessionHasErrors('body');

    expect($contactMessage->refresh()->status)->toBe(ContactMessageStatus::New)
        ->and($contactMessage->replies()->count())->toBe(0);
});

test('super admins can close and reopen a message', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $contactMessage = ContactMessage::factory()->create();

    $this->actingAs($superAdmin)
        ->patch(route('admin.super.contact-messages.status', $contactMessage), ['status' => 'closed'])
        ->assertRedirect();

    expect($contactMessage->refresh()->status)->toBe(ContactMessageStatus::Closed);

    $this->actingAs($superAdmin)
        ->patch(route('admin.super.contact-messages.status', $contactMessage), ['status' => 'new']);

    expect($contactMessage->refresh()->status)->toBe(ContactMessageStatus::New);
});
