<?php

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;

test('admins can moderate trips across accounts', function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->planned()->create([
        'title' => 'Goa Summer Escape',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.trips.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/trips/Index')
            ->has('trips.data', 1)
            ->where('trips.data.0.title', 'Goa Summer Escape')
            ->where('trips.data.0.owner.email', $owner->email));

    $this->actingAs($admin)
        ->patch(route('admin.trips.status', $trip), [
            'status' => TripStatus::Archived->value,
        ])
        ->assertRedirect();

    expect($trip->refresh()->status)->toBe(TripStatus::Archived);

    $this->actingAs($admin)
        ->delete(route('admin.trips.destroy', $trip))
        ->assertRedirect();

    expect(Trip::query()->find($trip->id))->toBeNull();
});

test('admins can view another users trip', function () {
    skipUnlessMongoDbAvailable();

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($admin)
        ->get(route('trips.show', $trip))
        ->assertOk();
});

test('regular users cannot access trip moderation', function () {
    skipUnlessMongoDbAvailable();

    $user = User::factory()->create();
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($user)
        ->get(route('admin.trips.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.trips.status', $trip), [
            'status' => TripStatus::Archived->value,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.trips.destroy', $trip))
        ->assertForbidden();
});

test('trip moderation supports search filters', function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();

    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    Trip::factory()->forUser($owner)->create(['title' => 'Rajasthan Heritage']);
    Trip::factory()->forUser($owner)->road()->create(['title' => 'Coastal Drive']);

    $this->actingAs($admin)
        ->get(route('admin.trips.index', ['search' => 'Rajasthan']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('trips.data', 1)
            ->where('trips.data.0.title', 'Rajasthan Heritage'));
});
