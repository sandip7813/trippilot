<?php

use App\Services\Trains\RailRadarClient;
use App\Services\Trains\TripTrainHaltsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
        'integrations.trains.cache_ttl' => 3600,
    ]);

    Cache::flush();
});

test('trip train halts service filters route stops between segment stations', function () {
    Http::fake([
        'api.railradar.in/v1/trains/12345*' => Http::response([
            'success' => true,
            'data' => [
                'route' => [
                    [
                        'sequence' => 1,
                        'stationCode' => 'NDLS',
                        'stationName' => 'New Delhi',
                        'scheduledDeparture' => '2026-07-10T06:00:00+05:30',
                        'distance' => 0,
                        'isHalt' => true,
                    ],
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

    $result = app(TripTrainHaltsService::class)->forSegment('12345', [
        'from' => 'BCT',
        'to' => 'BHP',
        'date' => '2026-07-10',
        'from_sequence' => 3,
        'to_sequence' => 18,
    ]);

    expect($result['available'])->toBeTrue()
        ->and($result['halt_count'])->toBe(1)
        ->and($result['halts'])->toHaveCount(3)
        ->and($result['halts'][0]['arrival'])->toBeNull()
        ->and($result['halts'][0]['departure'])->toBe('06:15')
        ->and($result['halts'][1]['arrival'])->toBe('07:00')
        ->and($result['halts'][1]['departure'])->toBe('07:05')
        ->and($result['halts'][2]['arrival'])->toBe('18:40')
        ->and($result['halts'][2]['departure'])->toBeNull();
});

test('trip train halts service uses sta and std when arrival and departure are duplicated', function () {
    Http::fake([
        'api.railradar.in/v1/trains/12345/live*' => Http::response([
            'success' => true,
            'data' => [
                'route' => [
                    [
                        'sequence' => 1,
                        'stationCode' => 'BCT',
                        'stationName' => 'Mumbai Central',
                        'scheduledDeparture' => '2026-07-10T06:15:00+05:30',
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 2,
                        'stationCode' => 'KYN',
                        'stationName' => 'Kalyan Junction',
                        'arrival' => '07:00:00',
                        'departure' => '07:00:00',
                        'sta' => '07:00',
                        'std' => '07:05',
                        'haltMinutes' => 5,
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 3,
                        'stationCode' => 'BHP',
                        'stationName' => 'Bolpur Shantiniketan',
                        'scheduledArrival' => '2026-07-11T18:40:00+05:30',
                        'isHalt' => true,
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(TripTrainHaltsService::class)->forSegment('12345', [
        'from' => 'BCT',
        'to' => 'BHP',
        'date' => '2026-07-10',
    ]);

    expect($result['halts'][1]['arrival'])->toBe('07:00')
        ->and($result['halts'][1]['departure'])->toBe('07:05');
});

test('trip train halts service infers departure from halt minutes when times match', function () {
    Http::fake([
        'api.railradar.in/v1/trains/12345/live*' => Http::response([
            'success' => true,
            'data' => [
                'route' => [
                    [
                        'sequence' => 1,
                        'stationCode' => 'BCT',
                        'scheduledDeparture' => '2026-07-10T06:15:00+05:30',
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 2,
                        'stationCode' => 'KYN',
                        'scheduledArrival' => '2026-07-10T07:00:00+05:30',
                        'scheduledDeparture' => '2026-07-10T07:00:00+05:30',
                        'haltMinutes' => 10,
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 3,
                        'stationCode' => 'BHP',
                        'scheduledArrival' => '2026-07-11T18:40:00+05:30',
                        'isHalt' => true,
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(TripTrainHaltsService::class)->forSegment('12345', [
        'from' => 'BCT',
        'to' => 'BHP',
        'date' => '2026-07-10',
    ]);

    expect($result['halts'][1]['arrival'])->toBe('07:00')
        ->and($result['halts'][1]['departure'])->toBe('07:10');
});

test('trip train halts service falls back to live endpoint when schedule lookup fails', function () {
    Http::fake([
        'api.railradar.in/v1/trains/12345' => Http::response([], 404),
        'api.railradar.in/v1/trains/12345/live*' => Http::response([
            'success' => true,
            'data' => [
                'route' => [
                    [
                        'sequence' => 1,
                        'stationCode' => 'BCT',
                        'stationName' => 'Mumbai Central',
                        'scheduledDeparture' => '2026-07-10T06:15:00+05:30',
                        'isHalt' => true,
                    ],
                    [
                        'sequence' => 2,
                        'stationCode' => 'BHP',
                        'stationName' => 'Bolpur Shantiniketan',
                        'scheduledArrival' => '2026-07-11T18:40:00+05:30',
                        'isHalt' => true,
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(TripTrainHaltsService::class)->forSegment('12345', [
        'from' => 'BCT',
        'to' => 'BHP',
    ]);

    expect($result['available'])->toBeTrue()
        ->and($result['halts'])->toHaveCount(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/trains/12345/live'));
});

test('railradar train schedule endpoint does not send haltsOnly query param', function () {
    config([
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
    ]);

    Http::fake([
        'api.railradar.in/v1/trains/12345*' => Http::response([
            'success' => true,
            'data' => ['route' => []],
        ]),
    ]);

    app(RailRadarClient::class)->trainSchedule('12345', '2026-07-10');

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.railradar.in/v1/trains/12345')
            && ($query['date'] ?? null) === '2026-07-10'
            && ! array_key_exists('haltsOnly', $query);
    });
});
