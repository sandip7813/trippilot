<?php

use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('guests see upcoming and past open trips but not private ones', function () {
    $owner = User::factory()->create();

    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Himalayan Trek',
        'destination' => ['label' => 'Manali, Himachal Pradesh, India'],
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
    ]);
    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Finished Ride',
        'destination' => ['label' => 'Manali, Himachal Pradesh, India'],
        'start_date' => now()->subDays(20)->toDateString(),
        'end_date' => now()->subDays(15)->toDateString(),
    ]);
    Trip::factory()->forUser($owner)->create(['title' => 'Private Getaway']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('stats.open_trips', 1)
            ->where('stats.destinations', 1)
            ->has('openTrips', 1)
            ->where('openTrips.0.title', 'Himalayan Trek')
            ->where('openTrips.0.organizer.name', $owner->first_name)
            ->has('pastOpenTrips', 1)
            ->where('pastOpenTrips.0.title', 'Finished Ride')
            ->has('destinations', 1)
            ->where('destinations.0.name', 'Manali')
            ->where('destinations.0.region', 'Himachal Pradesh, India')
            ->where('destinations.0.trip_count', 2)
            ->where('categories.0', ['value' => 'trek', 'count' => 1])
            ->where('myTrips', null));
});

test('the home page never leaks owner-only open trip data', function () {
    $owner = User::factory()->create(['email' => 'owner-secret@example.test']);
    $member = User::factory()->create();

    Trip::factory()->forUser($owner)->openTrip()
        ->withCollaborator($member, TripCollaboratorRole::Member)
        ->create([
            'budget' => 987654,
            'notes' => 'Confidential planning notes',
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
    Trip::factory()->forUser($owner)->create([
        'title' => 'Secret private trip',
        'destination' => ['label' => 'Hidden Valley'],
    ]);

    $payload = json_encode($this->get(route('home'))->viewData('page')['props']);

    expect($payload)
        ->not->toContain('987654')
        ->not->toContain('Confidential planning notes')
        ->not->toContain('Secret private trip')
        ->not->toContain('Hidden Valley')
        ->not->toContain($owner->email)
        ->not->toContain($member->email);
});

test('signed in users see their own upcoming and past trips', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();

    Trip::factory()->forUser($user)->create([
        'title' => 'Later Trip',
        'start_date' => now()->addDays(30)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
    ]);
    Trip::factory()->forUser($otherOwner)->withCollaborator($user)->create([
        'title' => 'Ongoing Joined Trip',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);
    Trip::factory()->forUser($user)->create([
        'title' => 'Past Trip',
        'start_date' => now()->subDays(20)->toDateString(),
        'end_date' => now()->subDays(15)->toDateString(),
    ]);
    Trip::factory()->forUser($otherOwner)->create(['title' => 'Someone Else Trip']);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('myTrips.counts.upcoming', 1)
            ->where('myTrips.counts.ongoing', 1)
            ->where('myTrips.counts.past', 1)
            ->has('myTrips.upcoming', 2)
            ->where('myTrips.upcoming.0.title', 'Ongoing Joined Trip')
            ->where('myTrips.upcoming.0.is_owner', false)
            ->where('myTrips.upcoming.1.title', 'Later Trip')
            ->has('myTrips.past', 1)
            ->where('myTrips.past.0.title', 'Past Trip'));
});

test('search suggestions match destinations and titles of upcoming open trips only', function () {
    $owner = User::factory()->create();

    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Manali Snow Trek',
        'destination' => ['label' => 'Manali, Himachal Pradesh, India'],
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
    ]);
    Trip::factory()->forUser($owner)->openTrip()->create([
        'title' => 'Old Manali Ride',
        'destination' => ['label' => 'Manali, Himachal Pradesh, India'],
        'start_date' => now()->subDays(20)->toDateString(),
        'end_date' => now()->subDays(15)->toDateString(),
    ]);
    Trip::factory()->forUser($owner)->create([
        'title' => 'Private Manali Plan',
        'destination' => ['label' => 'Manali Private Valley'],
    ]);

    $this->getJson(route('open-trips.suggestions', ['q' => 'mana']))
        ->assertOk()
        ->assertJsonCount(1, 'destinations')
        ->assertJsonPath('destinations.0.name', 'Manali')
        ->assertJsonPath('destinations.0.trip_count', 1)
        ->assertJsonCount(1, 'trips')
        ->assertJsonPath('trips.0.title', 'Manali Snow Trek');
});

test('search suggestions require at least two characters', function () {
    $this->getJson(route('open-trips.suggestions', ['q' => 'm']))
        ->assertUnprocessable();
});
