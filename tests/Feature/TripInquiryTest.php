<?php

use App\Models\Trip;
use App\Models\TripInquiry;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    TripInquiry::query()->whereNotNull('_id')->delete();
});

test('a logged in user can send an inquiry on a public trip', function () {
    $owner = User::factory()->create();
    $sender = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($sender)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Question about gear',
        'body' => 'Do I need my own tent?',
    ])->assertRedirect();

    $inquiry = TripInquiry::query()->where('trip_id', (string) $trip->id)->first();

    expect($inquiry)->not->toBeNull()
        ->and($inquiry->sender_id)->toBe($sender->id)
        ->and($inquiry->messageList())->toHaveCount(1);
});

test('a guest cannot send an inquiry', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Hi',
        'body' => 'Can I join?',
    ])->assertRedirect(route('login'));
});

test('an owner cannot inquire on their own trip', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($owner)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Hi',
        'body' => 'Testing',
    ])->assertForbidden();
});

test('an inquiry cannot be sent on a private trip', function () {
    $owner = User::factory()->create();
    $sender = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($sender)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Hi',
        'body' => 'Testing',
    ])->assertForbidden();
});

test('the owner sees an inbox of inquiries and can reply', function () {
    $owner = User::factory()->create();
    $sender = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($sender)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Question',
        'body' => 'Initial message',
    ]);

    $inquiry = TripInquiry::query()->where('trip_id', (string) $trip->id)->first();

    $this->actingAs($owner)->get(route('trips.inquiries.index', $trip))
        ->assertInertia(fn ($page) => $page
            ->has('inquiries', 1)
            ->where('inquiries.0.sender_name', $sender->name));

    $this->actingAs($owner)
        ->post(route('trips.inquiries.reply', [$trip, $inquiry]), ['body' => 'Yes, bring your own tent.'])
        ->assertRedirect();

    expect($inquiry->fresh()->messageList())->toHaveCount(2);
});

test('a stranger cannot view the inbox or reply to someone elses thread', function () {
    $owner = User::factory()->create();
    $sender = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($sender)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Question',
        'body' => 'Initial message',
    ]);
    $inquiry = TripInquiry::query()->where('trip_id', (string) $trip->id)->first();

    $this->actingAs($stranger)->get(route('trips.inquiries.index', $trip))->assertForbidden();
    $this->actingAs($stranger)
        ->post(route('trips.inquiries.reply', [$trip, $inquiry]), ['body' => 'butting in'])
        ->assertForbidden();
});

test('the sender never sees the owner email and vice versa in the inbox payload', function () {
    $owner = User::factory()->create(['email' => 'owner-secret@example.test']);
    $sender = User::factory()->create(['email' => 'sender-secret@example.test']);
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $this->actingAs($sender)->post(route('trips.inquiries.store', $trip), [
        'subject' => 'Question',
        'body' => 'Initial message',
    ]);

    $response = $this->actingAs($owner)->get(route('trips.inquiries.index', $trip));
    $payload = json_encode($response->viewData('page')['props']['inquiries']);

    expect($payload)
        ->not->toContain('owner-secret@example.test')
        ->not->toContain('sender-secret@example.test');
});
