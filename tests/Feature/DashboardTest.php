<?php

use App\Models\Trip;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    skipUnlessMongoDbAvailable();

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows trips invited by other users', function () {
    skipUnlessMongoDbAvailable();

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
