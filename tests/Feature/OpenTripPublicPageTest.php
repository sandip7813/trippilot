<?php

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('a guest can view a public open trip overview', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $response = $this->get(route('open-trips.show', $trip));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('OpenTrips/Show')
        ->where('trip.id', (string) $trip->id)
        ->where('trip.title', $trip->title)
        ->where('trip.organizer.name', $owner->first_name)
    );
});

test('a private trip is not publicly viewable', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->get(route('open-trips.show', $trip))->assertForbidden();
});

test('the public overview never leaks owner-only data', function () {
    $owner = User::factory()->create(['email' => 'owner-secret@example.test']);
    $collaborator = User::factory()->create();

    $trip = Trip::factory()->forUser($owner)->openTrip()
        ->withCollaborator($collaborator, TripCollaboratorRole::Member)
        ->create([
            'budget' => 123456,
            'notes' => 'Confidential planning notes',
            'chat_messages' => [['role' => 'user', 'content' => 'secret itinerary chat']],
        ]);

    $response = $this->get(route('open-trips.show', $trip));
    $payload = json_encode($response->viewData('page')['props']['trip']);

    expect($payload)
        ->not->toContain('123456')
        ->not->toContain('Confidential planning notes')
        ->not->toContain('secret itinerary chat')
        ->not->toContain($owner->email)
        ->not->toContain($collaborator->email)
        ->not->toContain($collaborator->first_name);
});

test('the discover listing only shows published upcoming trips by default', function () {
    $owner = User::factory()->create();

    $public = Trip::factory()->forUser($owner)->openTrip()->create(['title' => 'Public trek']);
    Trip::factory()->forUser($owner)->create(['title' => 'Private trip']);
    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Past open trip',
        'end_date' => now()->subWeek(),
    ]);

    $response = $this->get(route('open-trips.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('OpenTrips/Index')
        ->has('trips.data', 1)
        ->where('trips.data.0.title', 'Public trek')
    );
});

test('the discover listing can show past open trips on request', function () {
    $owner = User::factory()->create();
    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Past open trip',
        'end_date' => now()->subWeek(),
    ]);

    $response = $this->get(route('open-trips.index', ['past' => 1]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('trips.data', 1)
        ->where('trips.data.0.title', 'Past open trip')
        ->where('trips.data.0.is_past', true)
    );
});

test('a past open trip is not joinable but is still viewable', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create(['end_date' => now()->subDay()]);

    $response = $this->get(route('open-trips.show', $trip));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('trip.is_past', true)
        ->where('trip.is_joinable', false)
    );
});

test('member names stay hidden on the public page unless the owner opts in', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->openTrip()->create();

    $response = $this->get(route('open-trips.show', $trip));

    $response->assertInertia(fn ($page) => $page
        ->where('trip.group.member_names_visible', false)
    );
});

test('the public overview includes route points and an itinerary preview when shared', function () {
    $owner = User::factory()->create();

    $trip = Trip::factory()->forUser($owner)->openTrip()->withItinerary()->create([
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
        'origin' => ['label' => 'Mumbai, India', 'lat' => 19.076, 'lng' => 72.8777],
        'destination' => ['label' => 'Goa, India', 'lat' => 15.2993, 'lng' => 74.124],
        'waypoints' => [
            ['sequence' => 0, 'location' => ['label' => 'Pune, India', 'lat' => 18.5204, 'lng' => 73.8567]],
        ],
    ]);

    $response = $this->get(route('open-trips.show', $trip));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('trip.route.map_points', 4)
        ->where('trip.route.map_points.0.label', 'Mumbai, India')
        ->where('trip.route.map_points.1.label', 'Pune, India')
        ->where('trip.route.map_points.2.label', 'Goa, India')
        ->where('trip.route.map_points.3.label', 'Mumbai, India')
        ->where('trip.route.chain.0', 'Mumbai, India')
        ->has('trip.route.timeline', 4)
        ->where('trip.route.timeline.0.kind', 'origin')
        ->where('trip.route.timeline.1.label', 'Pune, India')
        ->whereType('trip.route.timeline.1.nights', 'integer')
        ->where('trip.itinerary.summary', 'Sample generated plan.')
        ->has('trip.itinerary.days', 1)
        ->missing('trip.itinerary.budget_breakdown')
    );
});

test('the itinerary preview stays hidden when the owner has not opted to share it', function () {
    $owner = User::factory()->create();

    $trip = Trip::factory()->forUser($owner)->openTrip()->withItinerary()->create([
        'open_trip' => [
            'category' => 'trek',
            'max_group_size' => 8,
            'cost_model' => 'cost_sharing',
            'cost_amount' => 6000.0,
            'cost_currency' => 'INR',
            'share_itinerary_with_members' => false,
        ],
    ]);

    $response = $this->get(route('open-trips.show', $trip));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('trip.itinerary', null)
    );
});
