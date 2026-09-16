<?php

use App\Actions\Trips\ResolvePendingTripCollaborators;
use App\Enums\TripCollaboratorRole;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('owner can add a collaborator by email', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => $collaborator->email,
            'role' => 'editor',
        ])
        ->assertRedirect();

    $trip->refresh();

    expect($trip->collaboratorRole($collaborator)?->value)->toBe('editor');
});

test('non owner cannot add a collaborator', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => $collaborator->email,
            'role' => 'editor',
        ])
        ->assertForbidden();
});

test('inviting an email with no account adds a pending collaborator', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => 'nobody@example.com',
            'role' => 'editor',
        ])
        ->assertRedirect();

    $trip->refresh();
    $collaborators = $trip->collaboratorsForFrontend();

    expect($collaborators)->toHaveCount(1)
        ->and($collaborators[0]['email'])->toBe('nobody@example.com')
        ->and($collaborators[0]['status'])->toBe('pending')
        ->and($collaborators[0]['user_id'])->toBeNull();
});

test('registering with an invited email resolves the pending collaborator', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)->post(route('trips.collaborators.store', $trip), [
        'email' => 'future-user@example.com',
        'role' => 'editor',
    ]);

    $newUser = User::factory()->create(['email' => 'future-user@example.com']);
    app(ResolvePendingTripCollaborators::class)($newUser);

    $trip->refresh();

    expect($trip->collaboratorRole($newUser)?->value)->toBe('editor')
        ->and($trip->isViewableBy($newUser))->toBeTrue();
});

test('inviting an email without a top-level domain is rejected', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => 'tp.user.1@yopmail',
            'role' => 'editor',
        ])
        ->assertSessionHasErrors(['email']);

    expect($trip->fresh()->collaboratorsForFrontend())->toBeEmpty();
});

test('owner cannot add themselves as a collaborator', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => $owner->email,
            'role' => 'editor',
        ])
        ->assertSessionHasErrors(['email']);
});

test('viewer collaborator can see but not edit the trip', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($viewer)->create();

    $this->actingAs($viewer)
        ->get(route('trips.show', $trip))
        ->assertOk();

    $this->actingAs($viewer)
        ->put(route('trips.update', $trip), [
            'title' => 'New title',
            'type' => 'vacation',
            'travelers' => 2,
            'origin' => [
                'label' => 'Mumbai, India',
                'lat' => 19.076,
                'lng' => 72.8777,
                'place_id' => 'test-origin',
                'country_code' => 'in',
            ],
            'destination' => [
                'label' => 'Tokyo, Japan',
                'lat' => 35.6762,
                'lng' => 139.6503,
                'place_id' => 'test-destination',
                'country_code' => 'jp',
            ],
        ])
        ->assertForbidden();
});

test('editor collaborator can update the trip', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($editor, TripCollaboratorRole::Editor)->create(['title' => 'Old title']);

    $this->actingAs($editor)
        ->patch(route('trips.favorite', $trip))
        ->assertRedirect();

    expect($trip->fresh()->is_favorite)->toBeTrue();
});

test('collaborator without access cannot view the trip', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($stranger)
        ->get(route('trips.show', $trip))
        ->assertForbidden();
});

test('trips shared with a user appear under the shared filter', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    Trip::factory()->forUser($viewer)->create(['title' => 'My Trip']);
    Trip::factory()->forUser($owner)->withCollaborator($viewer)->create(['title' => 'Shared Trip']);

    $this->actingAs($viewer)
        ->get(route('trips.index', ['filter' => 'shared']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trips', 1)
            ->where('trips.0.title', 'Shared Trip'));
});

test('owner can remove a collaborator', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($collaborator)->create();

    $this->actingAs($owner)
        ->delete(route('trips.collaborators.destroy', [$trip, $collaborator->email]))
        ->assertRedirect();

    expect($trip->fresh()->isCollaborator($collaborator))->toBeFalse();
});

test('owner can change a collaborator role', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($collaborator)->create();

    $this->actingAs($owner)
        ->patch(route('trips.collaborators.update', [$trip, $collaborator->email]), ['role' => 'editor'])
        ->assertRedirect();

    expect($trip->fresh()->collaboratorRole($collaborator)?->value)->toBe('editor');
});

test('a collaborator sees who invited them, but the owner does not', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($viewer)->create();

    $ownerView = $trip->toFrontend($owner);
    expect($ownerView['is_owner'])->toBeTrue()
        ->and($ownerView['owner'])->toBeNull();

    $collaboratorView = $trip->toFrontend($viewer);
    expect($collaboratorView['is_owner'])->toBeFalse()
        ->and($collaboratorView['owner']['name'])->toBe($owner->name)
        ->and($collaboratorView['owner']['email'])->toBe($owner->email);
});

test('owner information is included on the trip show page for a collaborator', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->withCollaborator($viewer)->create();

    $this->actingAs($viewer)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trip.owner.name', $owner->name)
            ->where('trip.owner.email', $owner->email));
});

test('road trips can be shared and viewed by a collaborator', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->road()->create();

    $this->actingAs($owner)
        ->post(route('trips.collaborators.store', $trip), [
            'email' => $collaborator->email,
            'role' => 'viewer',
        ])
        ->assertRedirect();

    expect($trip->fresh()->collaboratorRole($collaborator)?->value)->toBe('viewer');

    $this->actingAs($collaborator)
        ->get(route('road-trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('RoadTrips/Show')
            ->where('trip.is_owner', false)
            ->where('trip.collaborator_role', 'viewer')
            ->where('trip.owner.email', $owner->email));
});
