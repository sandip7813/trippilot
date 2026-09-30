<?php

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('guests can view the contact page', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Contact')
            ->where('sender', null)
            ->has('topics', 6));
});

test('signed in users see the contact page with their details', function () {
    $user = User::factory()->create(['mobile_number' => '9876543210']);

    $this->actingAs($user)
        ->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sender.email', $user->email)
            ->where('sender.phone', '9876543210')
            ->where('recaptcha.enabled', false));
});

test('guests can send a contact message and super admins are notified', function () {
    Notification::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Guest',
        'email' => 'jane@example.com',
        'topic' => 'trip_planning',
        'subject' => 'Help with a Goa trip',
        'message' => 'Can you help me plan five days in Goa?',
    ]);

    $response->assertRedirect(route('contact'));

    $contactMessage = ContactMessage::query()->sole();

    expect($contactMessage)
        ->user_id->toBeNull()
        ->name->toBe('Jane Guest')
        ->email->toBe('jane@example.com')
        ->status->toBe(ContactMessageStatus::New);

    $response->assertInertiaFlash('contactReference', $contactMessage->reference());

    Notification::assertSentTo($superAdmin, ContactMessageReceivedNotification::class);
    Notification::assertNotSentTo($admin, ContactMessageReceivedNotification::class);
});

test('signed in users send messages from their account details', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('contact.store'), [
        'name' => 'Someone Else',
        'email' => 'spoofed@example.com',
        'topic' => 'feedback',
        'subject' => 'Love the app',
        'message' => 'The road trip planner is brilliant.',
    ])->assertSessionHasNoErrors();

    expect(ContactMessage::query()->sole())
        ->user_id->toBe($user->id)
        ->name->toBe($user->name)
        ->email->toBe($user->email);
});

test('guests must provide their name and email', function () {
    $this->post(route('contact.store'), [
        'topic' => 'general',
        'subject' => 'Hello',
        'message' => 'Just saying hello to the team.',
    ])->assertSessionHasErrors(['name', 'email']);

    expect(ContactMessage::query()->count())->toBe(0);
});

test('contact messages require a valid topic, a subject and a meaningful message', function () {
    $this->post(route('contact.store'), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'topic' => 'not-a-topic',
        'subject' => '',
        'message' => 'Hi',
    ])->assertSessionHasErrors(['topic', 'subject', 'message']);
});

test('guests must pass recaptcha when it is configured', function () {
    config([
        'recaptcha.enabled' => true,
        'recaptcha.site_key' => 'site-key',
        'recaptcha.secret_key' => 'secret-key',
    ]);
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'contact',
        ]),
    ]);

    $payload = [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'topic' => 'general',
        'subject' => 'Hello',
        'message' => 'Just saying hello to the team.',
    ];

    $this->post(route('contact.store'), $payload)
        ->assertSessionHasErrors('g-recaptcha-response');

    $this->post(route('contact.store'), [...$payload, 'g-recaptcha-response' => 'token'])
        ->assertSessionHasNoErrors();

    expect(ContactMessage::query()->count())->toBe(1);
});

test('signed in users skip recaptcha', function () {
    config([
        'recaptcha.enabled' => true,
        'recaptcha.site_key' => 'site-key',
        'recaptcha.secret_key' => 'secret-key',
    ]);
    Http::fake();

    $this->actingAs(User::factory()->create())->post(route('contact.store'), [
        'topic' => 'general',
        'subject' => 'Hello',
        'message' => 'Just saying hello to the team.',
    ])->assertSessionHasNoErrors();

    Http::assertNothingSent();
});

test('guests must give an email with a proper domain', function () {
    $this->post(route('contact.store'), [
        'name' => 'Jane',
        'email' => 'dfsdfds@asd',
        'topic' => 'general',
        'subject' => 'Hello',
        'message' => 'Just saying hello to the team.',
    ])->assertSessionHasErrors('email');

    expect(ContactMessage::query()->count())->toBe(0);
});

test('super admins are emailed about new contact messages', function () {
    Notification::fake();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->post(route('contact.store'), [
        'name' => 'Jane Guest',
        'email' => 'jane@example.com',
        'phone' => '9876543210',
        'topic' => 'problem',
        'subject' => 'Map not loading',
        'message' => 'The road trip map stays blank on my phone.',
    ]);

    Notification::assertSentTo(
        $superAdmin,
        ContactMessageReceivedNotification::class,
        fn (ContactMessageReceivedNotification $notification, array $channels) => in_array('mail', $channels, true)
            && in_array('database', $channels, true),
    );
});

test('the new contact message email is queued and includes the message details', function () {
    $contactMessage = ContactMessage::factory()->create([
        'name' => 'Jane Guest',
        'email' => 'jane@example.com',
        'subject' => 'Map not loading',
        'message' => 'The road trip map stays blank on my phone.',
    ]);
    $superAdmin = User::factory()->superAdmin()->create();

    $notification = ContactMessageReceivedNotification::forMessage($contactMessage);
    $mail = $notification->toMail($superAdmin);
    $html = (string) $mail->render();

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($mail->subject)->toBe("New contact message: Map not loading [{$contactMessage->reference()}]")
        ->and($html)->toContain('Jane Guest')
        ->toContain('jane@example.com')
        ->toContain('The road trip map stays blank on my phone.')
        ->toContain($contactMessage->reference())
        ->toContain($contactMessage->created_at->format(ContactMessage::EMAIL_DATE_FORMAT))
        ->toContain(route('admin.super.contact-messages.show', $contactMessage));
});
