<?php

use App\Enums\TripScope;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function domesticTripLocationPayload(string $originLabel, string $destLabel, array $originOverrides = [], array $destOverrides = []): array
{
    return [
        'origin' => array_merge([
            'label' => $originLabel,
            'lat' => 19.076,
            'lng' => 72.8777,
            'place_id' => 'test-origin',
            'country_code' => 'in',
        ], $originOverrides),
        'destination' => array_merge([
            'label' => $destLabel,
            'lat' => 23.2324,
            'lng' => 87.6810,
            'place_id' => 'test-destination',
            'country_code' => 'in',
        ], $destOverrides),
    ];
}

/**
 * @return array<string, mixed>
 */
function trainsBetweenResponse(string $fromCode, string $toCode, string $trainNumber = '12345'): array
{
    return [
        'success' => true,
        'data' => [
            'from' => ['code' => $fromCode, 'name' => "{$fromCode} Station"],
            'to' => ['code' => $toCode, 'name' => "{$toCode} Station"],
            'count' => 1,
            'trains' => [
                [
                    'train' => [
                        'number' => $trainNumber,
                        'name' => 'Test Express',
                        'type' => 'Express',
                        'category' => 'Superfast',
                        'runDays' => ['mon', 'wed', 'fri'],
                    ],
                    'from' => ['departure' => '06:15', 'day' => 1, 'sequence' => 3],
                    'to' => ['arrival' => '18:40', 'day' => 2, 'sequence' => 18],
                    'duration' => 985,
                    'distance' => 2100.5,
                    'totalHaltsBetween' => 6,
                ],
            ],
        ],
    ];
}

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();

    config([
        'integrations.trains.driver' => 'railradar',
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
        'integrations.trains.cache_ttl' => 3600,
        'integrations.trains.station_lookup_cache_ttl' => 3600,
    ]);
});

test('trip show includes outbound and return train timings for domestic trips', function () {
    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'BCT' => 'Mumbai Central',
                'BHP' => 'Bolpur Shantiniketan',
            ],
        ]),
        'api.railradar.in/v1/trains/between/BCT/BHP*' => Http::response(
            trainsBetweenResponse('BCT', 'BHP', '12345'),
        ),
        'api.railradar.in/v1/trains/between/BHP/BCT*' => Http::response(
            trainsBetweenResponse('BHP', 'BCT', '54321'),
        ),
    ]);

    $user = User::factory()->create();
    $startDate = now()->addWeek()->toDateString();
    $endDate = now()->addWeeks(2)->toDateString();

    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::Domestic,
        ...domesticTripLocationPayload('Mumbai, India', 'Shantiniketan, India'),
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->where('trainTimings.available', true)
            ->where('trainTimings.outbound.available', true)
            ->where('trainTimings.outbound.date', $startDate)
            ->where('trainTimings.outbound.trains.0.number', '12345')
            ->where('trainTimings.outbound.trains.0.duration_label', '16h 25m')
            ->where('trainTimings.return.available', true)
            ->where('trainTimings.return.date', $endDate)
            ->where('trainTimings.return.trains.0.number', '54321'));
});

test('trip show explains when railradar api key is missing', function () {
    config(['integrations.trains.drivers.railradar.api_key' => null]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::Domestic,
        ...domesticTripLocationPayload('Mumbai, India', 'Shantiniketan, India'),
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trainTimings.available', false)
            ->where('trainTimings.reason', 'driver_disabled'));
});

test('trip show skips train availability for international trips', function () {
    config(['integrations.weather.driver' => 'openweathermap']);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::International,
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => 35.6762,
            'lng' => 139.6503,
            'country_code' => 'jp',
        ],
    ]);

    Http::fake();

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trainTimings.available', false)
            ->where('trainTimings.reason', 'not_domestic'));

    Http::assertNothingSent();
});

test('trip show falls back to nearest railhead when no direct trains exist', function () {
    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'HWH' => 'Howrah Junction',
                'KLK' => 'Kalka',
                'SML' => 'Shimla',
            ],
        ]),
        'api.railradar.in/v1/trains/between/HWH/SML*' => Http::response([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'No trains found'],
        ], 404),
        'api.railradar.in/v1/trains/between/HWH/KLK*' => Http::response(
            trainsBetweenResponse('HWH', 'KLK', '12301'),
        ),
        'api.railradar.in/v1/trains/between/SML/HWH*' => Http::response([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'No trains found'],
        ], 404),
        'api.railradar.in/v1/trains/between/KLK/HWH*' => Http::response(
            trainsBetweenResponse('KLK', 'HWH', '12302'),
        ),
    ]);

    $user = User::factory()->create();
    $startDate = now()->addWeek()->toDateString();
    $endDate = now()->addWeeks(2)->toDateString();

    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::Domestic,
        ...domesticTripLocationPayload('Kolkata, India', 'Shimla, India', [
            'lat' => 22.5726,
            'lng' => 88.3639,
        ], [
            'lat' => 31.1048,
            'lng' => 77.1734,
        ]),
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Trips/Show')
            ->where('trainTimings.available', true)
            ->where('trainTimings.uses_railhead_fallback', true)
            ->where('trainTimings.destination_railhead.station.code', 'KLK')
            ->where('trainTimings.outbound.search_mode', 'railhead')
            ->where('trainTimings.outbound.trains.0.number', '12301')
            ->where('trainTimings.return.search_mode', 'railhead')
            ->where('trainTimings.return.trains.0.number', '12302'));
});

test('trip show handles no trains on travel dates', function () {
    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'BCT' => 'Mumbai Central',
                'BHP' => 'Bolpur Shantiniketan',
            ],
        ]),
        'api.railradar.in/v1/trains/between/*' => Http::response([
            'success' => false,
            'error' => ['code' => 'NOT_FOUND', 'message' => 'No trains found'],
        ], 404),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::Domestic,
        ...domesticTripLocationPayload('Mumbai, India', 'Bolpur, India'),
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trainTimings.available', false)
            ->where('trainTimings.reason', 'no_trains')
            ->where('trainTimings.outbound.available', false)
            ->where('trainTimings.return.available', false));
});

test('users can fetch train halts for their trip', function () {
    Http::fake([
        'api.railradar.in/v1/trains/12345*' => Http::response([
            'success' => true,
            'data' => [
                'route' => [
                    [
                        'sequence' => 3,
                        'stationCode' => 'BCT',
                        'stationName' => 'Mumbai Central',
                        'scheduledDeparture' => '2026-07-10T06:15:00+05:30',
                        'distance' => 0,
                        'platform' => '2',
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 4,
                        'stationCode' => 'KYN',
                        'stationName' => 'Kalyan Junction',
                        'scheduledArrival' => '2026-07-10T07:00:00+05:30',
                        'scheduledDeparture' => '2026-07-10T07:05:00+05:30',
                        'distance' => 54,
                        'haltMinutes' => 5,
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 18,
                        'stationCode' => 'BHP',
                        'stationName' => 'Bolpur Shantiniketan',
                        'scheduledArrival' => '2026-07-11T18:40:00+05:30',
                        'distance' => 2100,
                        'isHalt' => true,
                    ],
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'trip_scope' => TripScope::Domestic,
        ...domesticTripLocationPayload('Mumbai, India', 'Shantiniketan, India'),
    ]);

    $this->actingAs($user)
        ->getJson(route('trips.trains.halts', [
            'trip' => $trip,
            'trainNumber' => '12345',
            'from' => 'BCT',
            'to' => 'BHP',
            'date' => '2026-07-10',
            'from_sequence' => 3,
            'to_sequence' => 18,
        ]))
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('halt_count', 1)
        ->assertJsonCount(3, 'halts')
        ->assertJsonPath('halts.0.code', 'BCT')
        ->assertJsonPath('halts.2.code', 'BHP');
});
