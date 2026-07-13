<?php

use App\Enums\TripRouteMode;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\TripRouteResolver;

/**
 * @return array<string, mixed>
 */
function multiCityTripPayload(): array
{
    return [
        'title' => 'Golden Triangle from Kolkata',
        'type' => 'vacation',
        'travelers' => 2,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
        'route_mode' => 'multi_city',
        'returns_to_origin' => '1',
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
            'country_code' => 'in',
        ],
        'waypoints' => [
            [
                'sequence' => 1,
                'location' => [
                    'label' => 'Delhi, India',
                    'lat' => 28.6139,
                    'lng' => 77.2090,
                    'country_code' => 'in',
                ],
                'nights' => 3,
            ],
            [
                'sequence' => 2,
                'location' => [
                    'label' => 'Agra, India',
                    'lat' => 27.1767,
                    'lng' => 78.0081,
                    'country_code' => 'in',
                ],
                'nights' => 1,
            ],
            [
                'sequence' => 3,
                'location' => [
                    'label' => 'Jaipur, India',
                    'lat' => 26.9124,
                    'lng' => 75.7873,
                    'country_code' => 'in',
                ],
                'nights' => 2,
            ],
        ],
        'destination' => [
            'label' => 'Jaipur, India',
            'lat' => 26.9124,
            'lng' => 75.7873,
            'country_code' => 'in',
        ],
    ];
}

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('user can create a multi city vacation trip', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('trips.store'), multiCityTripPayload());

    $response->assertRedirect();

    $trip = Trip::query()->where('title', 'Golden Triangle from Kolkata')->first();

    expect($trip)->not->toBeNull()
        ->and($trip->route_mode)->toBe(TripRouteMode::MultiCity)
        ->and($trip->returns_to_origin)->toBeTrue()
        ->and($trip->destination['label'] ?? null)->toBe('Jaipur, India')
        ->and($trip->waypoints)->toHaveCount(3);

    $summary = app(TripRouteResolver::class)->summary($trip);

    expect($summary['route_mode'])->toBe('multi_city')
        ->and($summary['leg_count'])->toBe(4)
        ->and($summary['stop_count'])->toBe(3);
});

test('trip show includes multi city route data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('trips.store'), multiCityTripPayload());

    $trip = Trip::query()->where('title', 'Golden Triangle from Kolkata')->firstOrFail();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trip.route_mode', 'multi_city')
            ->where('trip.returns_to_origin', true)
            ->where('trip.waypoints', fn ($waypoints) => count($waypoints) === 3)
            ->where('trip.route_summary.leg_count', 4));
});

test('create trip page includes route templates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('trips.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tripTemplates', 3)
            ->where('tripTemplates.0.key', 'golden_triangle'));
});
