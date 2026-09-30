<?php

use App\Enums\TripVisibility;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

/**
 * @return array<string, mixed>
 */
function baseTripPayload(): array
{
    return [
        'type' => 'vacation',
        'title' => 'Ladakh with friends',
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
            'place_id' => 'test-origin',
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Leh, India',
            'lat' => 34.1526,
            'lng' => 77.5771,
            'place_id' => 'test-place',
            'country_code' => 'in',
        ],
        'start_date' => now()->addWeeks(2)->toDateString(),
        'end_date' => now()->addWeeks(3)->toDateString(),
        'travelers' => 2,
    ];
}

test('filling in the group details on the create form publishes the trip immediately', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('trips.store'), [
            ...baseTripPayload(),
            'make_open_trip' => true,
            'open_trip' => [
                'category' => 'vacation',
                'max_group_size' => 6,
                'cost_model' => 'cost_sharing',
            ],
        ])
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip)->not->toBeNull()
        ->and($trip->isPublic())->toBeTrue()
        ->and($trip->openTripDetails()['category'])->toBe('vacation')
        ->and($trip->openTripDetails()['max_group_size'])->toBe(6);
});

test('checking make_open_trip creates the trip and sends the owner to fill in group details, without publishing yet', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('trips.store'), [...baseTripPayload(), 'make_open_trip' => true])
        ->assertRedirect(route('trips.edit', Trip::query()->where('user_id', $owner->id)->first()));

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    // Group details aren't collected on the create form, so publishing is
    // deferred until the owner fills them in on the edit page.
    expect($trip)->not->toBeNull()
        ->and($trip->isPublic())->toBeFalse();
});

test('publishing succeeds once the owner fills in the required group details', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('trips.store'), [...baseTripPayload(), 'make_open_trip' => true]);
    $trip = Trip::query()->where('user_id', $owner->id)->first();

    $this->actingAs($owner)->put(route('trips.open-trip.update', $trip), [
        'category' => 'vacation',
        'max_group_size' => 6,
        'cost_model' => 'cost_sharing',
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('trips.publish', $trip))->assertRedirect();

    expect($trip->fresh()->visibility)->toBe(TripVisibility::Public);
});

test('leaving make_open_trip unchecked keeps the trip private', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('trips.store'), baseTripPayload())->assertRedirect();

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip->isPublic())->toBeFalse();
});

test('the trip is still created even if publishing fails the active limit', function () {
    config(['trippilot.open_trips.max_active_per_user' => 0]);

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('trips.store'), [...baseTripPayload(), 'make_open_trip' => true])
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip)->not->toBeNull()
        ->and($trip->isPublic())->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function baseRoadTripPayload(): array
{
    return [
        'title' => 'Ladakh biking trip',
        'origin' => ['label' => 'Mumbai, India', 'lat' => 19.076, 'lng' => 72.8777, 'place_id' => 'o', 'country_code' => 'in'],
        'destination' => ['label' => 'Leh, India', 'lat' => 34.1526, 'lng' => 77.5771, 'place_id' => 'd', 'country_code' => 'in'],
        'start_date' => now()->addWeeks(2)->toDateString(),
        'end_date' => now()->addWeeks(3)->toDateString(),
        'travelers' => 2,
        'road_profile' => [
            'vehicle_class' => 'car',
            'fuel_type' => 'petrol',
            'driving_pace' => 'standard',
            'avoid_tolls' => false,
            'avoid_highways' => false,
        ],
    ];
}

test('checking make_open_trip on a road trip creates it and defers publishing until group details are filled in', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('road-trips.store'), [...baseRoadTripPayload(), 'make_open_trip' => true])
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip)->not->toBeNull()
        ->and($trip->isPublic())->toBeFalse();
});

test('a road trip publishes immediately when group details are filled in on the create form', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('road-trips.store'), [
        ...baseRoadTripPayload(),
        'make_open_trip' => true,
        'open_trip' => [
            'category' => 'bike',
            'max_group_size' => 4,
            'cost_model' => 'pay_own',
        ],
    ]);

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip->isPublic())->toBeTrue()
        ->and($trip->openTripDetails()['category'])->toBe('bike');
});

test('an invalid open_trip field at creation surfaces as a nested validation error', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('trips.store'), [
            ...baseTripPayload(),
            'open_trip' => ['max_group_size' => 0],
        ])
        ->assertSessionHasErrors('open_trip.max_group_size');
});

test('planning mode, travel style, and budget are not required when creating an open trip', function () {
    $owner = User::factory()->create();

    $payload = baseTripPayload();
    unset($payload['type']);

    $this->actingAs($owner)
        ->post(route('trips.store'), [
            ...$payload,
            'make_open_trip' => true,
            'open_trip' => [
                'category' => 'vacation',
                'max_group_size' => 6,
                'cost_model' => 'cost_sharing',
            ],
        ])
        ->assertSessionDoesntHaveErrors(['type', 'travel_style', 'budget'])
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $owner->id)->first();

    expect($trip)->not->toBeNull()
        ->and($trip->type->value)->toBe('vacation')
        ->and($trip->travel_style)->toBeNull()
        ->and($trip->budget)->toBeNull();
});

test('planning mode is still required when not creating an open trip', function () {
    $owner = User::factory()->create();

    $payload = baseTripPayload();
    unset($payload['type']);

    $this->actingAs($owner)
        ->post(route('trips.store'), $payload)
        ->assertSessionHasErrors('type');
});
