<?php

use App\Models\Trip;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    skipUnlessMongoDbAvailable();
    Trip::query()->whereNotNull('_id')->delete();

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows trips invited by other users', function () {
    skipUnlessMongoDbAvailable();
    Trip::query()->whereNotNull('_id')->delete();

    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    Trip::factory()->forUser($owner)->withCollaborator($viewer)->create(['title' => 'Invited Trip']);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.invited', 1)
            ->has('invitedTrips', 1)
            ->where('invitedTrips.0.title', 'Invited Trip'));
});

test('dashboard highlights the next departure and phase counts', function () {
    skipUnlessMongoDbAvailable();
    Trip::query()->whereNotNull('_id')->delete();

    $user = User::factory()->create();

    Trip::factory()->forUser($user)->create([
        'title' => 'Later Trip',
        'start_date' => now()->addDays(30)->toDateString(),
        'end_date' => now()->addDays(35)->toDateString(),
    ]);
    Trip::factory()->forUser($user)->create([
        'title' => 'Sooner Trip',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
    ]);
    Trip::factory()->forUser($user)->create([
        'title' => 'Past Trip',
        'start_date' => now()->subDays(20)->toDateString(),
        'end_date' => now()->subDays(15)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('nextTrip.title', 'Sooner Trip')
            ->where('stats.upcoming', now()->addDays(5)->toDateString())
            ->where('phaseCounts.upcoming', 2)
            ->where('phaseCounts.ongoing', 0)
            ->where('phaseCounts.past', 1)
            ->has('recentTrips', 3));
});

test('dashboard prefers a trip that is already under way', function () {
    skipUnlessMongoDbAvailable();
    Trip::query()->whereNotNull('_id')->delete();

    $user = User::factory()->create();

    Trip::factory()->forUser($user)->create([
        'title' => 'Upcoming Trip',
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);
    Trip::factory()->forUser($user)->create([
        'title' => 'Current Trip',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('nextTrip.title', 'Current Trip')
            ->where('phaseCounts.ongoing', 1));
});

test('dashboard has no next departure when nothing is scheduled', function () {
    skipUnlessMongoDbAvailable();
    Trip::query()->whereNotNull('_id')->delete();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('nextTrip', null)
            ->where('stats.trips', 0));
});
