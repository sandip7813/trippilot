<?php

use App\Models\Trip;
use App\Models\User;

test('users have a default role of user', function () {
    $user = User::factory()->create();

    expect($user->role->value)->toBe('user');
});

test('admin middleware blocks regular users from admin panel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('admin users can access admin dashboard', function () {
    skipUnlessMongoDbAvailable();

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertOk();
});

test('super admin users can access admin dashboard', function () {
    skipUnlessMongoDbAvailable();

    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertOk();
});

test('super admin middleware blocks admins from super admin settings', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('admin.super.settings'))->assertForbidden();
});

test('super admin users can access super admin settings', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $this->get(route('admin.super.settings'))->assertOk();
});

test('authenticated users can visit trip placeholder pages', function () {
    skipUnlessMongoDbAvailable();

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('trips.index'))->assertOk();
    $this->get(route('road-trips.index'))->assertOk();
});

test('mongodb connection can persist trip documents', function () {
    skipUnlessMongoDbAvailable();
    $trip = Trip::query()->create([
        'user_id' => 1,
        'type' => 'vacation',
        'title' => 'Test Trip',
        'status' => 'draft',
    ]);

    expect($trip->exists)->toBeTrue()
        ->and($trip->title)->toBe('Test Trip');

    $trip->delete();
})->group('mongodb');

test('inertia shares brand logo from config', function () {
    skipUnlessMongoDbAvailable();

    config(['trippilot.logo' => 'pin']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('brand.logo', 'pin'));
});
