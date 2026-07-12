<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function validRoadTripPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Mumbai to Pune Drive',
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
            'place_id' => 'test-origin',
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Pune, India',
            'lat' => 18.5204,
            'lng' => 73.8567,
            'place_id' => 'test-destination',
            'country_code' => 'in',
        ],
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
        'budget' => 5000,
        'travelers' => 2,
        'notes' => 'Scenic stops welcome',
        'road_profile' => [
            'vehicle_class' => 'car',
            'fuel_type' => 'petrol',
            'driving_pace' => 'standard',
            'food_preference' => 'any',
            'avoid_tolls' => false,
            'avoid_highways' => false,
        ],
    ], $overrides);
}

function fakeGeoapifyRouting(): void
{
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'https://api.geoapify.com/v1/routing*' => Http::response([
            'features' => [[
                'geometry' => [
                    'type' => 'MultiLineString',
                    'coordinates' => [
                        [
                            [72.8777, 19.076],
                            [73.8567, 18.5204],
                        ],
                    ],
                ],
                'properties' => [
                    'distance' => 150000,
                    'time' => 7200,
                    'toll' => false,
                    'legs' => [],
                ],
            ]],
        ]),
    ]);
}

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('guests cannot access road trip pages', function () {
    $this->get(route('road-trips.index'))->assertRedirect(route('login'));
    $this->get(route('road-trips.create'))->assertRedirect(route('login'));
});

test('authenticated users can list only their road trips', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Trip::factory()->forUser($user)->road()->create(['title' => 'My Drive']);
    Trip::factory()->forUser($other)->road()->create(['title' => 'Other Drive']);
    Trip::factory()->forUser($user)->create(['title' => 'Vacation Trip']);

    $this->actingAs($user)
        ->get(route('road-trips.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('RoadTrips/Index')
            ->has('trips', 1)
            ->where('trips.0.title', 'My Drive'));
});

test('users can create a road trip and calculate the route', function () {
    fakeGeoapifyRouting();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('road-trips.store'), validRoadTripPayload())
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $user->id)->first();

    expect($trip)->not->toBeNull()
        ->and($trip->type->value)->toBe('road')
        ->and($trip->road_profile['vehicle_class'] ?? null)->toBe('car')
        ->and($trip->routeData()['distance_km'] ?? null)->toBe(150.0)
        ->and($trip->routeData()['polyline'] ?? [])->not->toBeEmpty();
});

test('users can view their road trip show page', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->road()->create([
        'route' => [
            'distance_km' => 150,
            'duration_seconds' => 7200,
            'has_tolls' => false,
            'polyline' => [[19.076, 72.8777], [18.5204, 73.8567]],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('road-trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('RoadTrips/Show')
            ->where('trip.id', (string) $trip->id)
            ->has('amenityLayers'));
});

test('vacation trips cannot be viewed as road trips', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->get(route('road-trips.show', $trip))
        ->assertNotFound();
});

test('users can recalculate a road trip route', function () {
    fakeGeoapifyRouting();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->road()->create(['route' => null]);

    $this->actingAs($user)
        ->post(route('road-trips.route', $trip))
        ->assertRedirect();

    expect($trip->fresh()->routeData()['distance_km'] ?? null)->toBe(150.0);
});

test('users can accept a suggested break as a stop', function () {
    $user = User::factory()->create();
    $breakId = 'break-123';

    $trip = Trip::factory()->forUser($user)->road()->create([
        'suggested_breaks' => [[
            'id' => $breakId,
            'kind' => 'meal',
            'title' => 'Highway dhaba',
            'reason' => 'Good lunch stop',
            'sequence' => 1,
            'label' => 'Highway dhaba',
            'lat' => 18.9,
            'lng' => 73.2,
            'place_id' => 'place-1',
        ]],
        'stops' => [],
    ]);

    $this->actingAs($user)
        ->post(route('road-trips.accept-break', $trip), ['break_id' => $breakId])
        ->assertRedirect();

    $stops = $trip->fresh()->stops;

    expect($stops)->toHaveCount(1)
        ->and($stops[0]['label'])->toBe('Highway dhaba')
        ->and($stops[0]['source'])->toBe('ai_suggested');
});

test('users can remove a stop from a road trip', function () {
    $user = User::factory()->create();

    $trip = Trip::factory()->forUser($user)->road()->create([
        'stops' => [
            [
                'label' => 'Fuel stop',
                'lat' => 18.9,
                'lng' => 73.2,
                'address' => 'Highway Fuel Stop, Pune, Maharashtra, India',
            ],
            [
                'label' => 'Lunch break',
                'lat' => 18.95,
                'lng' => 73.25,
                'address' => 'Roadside Cafe, Pune, Maharashtra, India',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->delete(route('road-trips.remove-stop', $trip), ['stop_index' => 0])
        ->assertRedirect();

    $stops = $trip->fresh()->stops;

    expect($stops)->toHaveCount(1)
        ->and($stops[0]['label'])->toBe('Lunch break');
});

test('accepting a break stores the stop address when available', function () {
    $user = User::factory()->create();
    $breakId = 'break-with-address';

    $trip = Trip::factory()->forUser($user)->road()->create([
        'suggested_breaks' => [[
            'id' => $breakId,
            'kind' => 'fuel',
            'title' => 'Indian Oil',
            'reason' => 'Suggested stop along your route.',
            'sequence' => 1,
            'label' => 'Indian Oil',
            'lat' => 23.2,
            'lng' => 88.0,
            'place_id' => 'place-2',
            'address' => 'Indian Oil, Kolkata, West Bengal, India',
        ]],
        'stops' => [],
    ]);

    $this->actingAs($user)
        ->post(route('road-trips.accept-break', $trip), ['break_id' => $breakId])
        ->assertRedirect();

    expect($trip->fresh()->stops[0]['address'] ?? null)
        ->toBe('Indian Oil, Kolkata, West Bengal, India');
});
