<?php

use App\Services\Trains\RailRadarClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('railradar client requests trains between stations with bearer auth', function () {
    config([
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
        'integrations.trains.drivers.railradar.base_url' => 'https://api.railradar.in/v1',
    ]);

    Http::fake([
        'api.railradar.in/v1/trains/between/UJN/INDB*' => Http::response([
            'success' => true,
            'data' => ['count' => 0, 'trains' => []],
        ]),
    ]);

    $response = app(RailRadarClient::class)->trainsBetween('UJN', 'INDB', '2026-07-05');

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.railradar.in/v1/trains/between/UJN/INDB')
            && ($query['date'] ?? null) === '2026-07-05'
            && ($query['live'] ?? null) === 'false'
            && $request->hasHeader('Authorization', 'Bearer rr_test_key');
    });
});

test('railradar client fetches station lookup directory', function () {
    config([
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
    ]);

    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => ['NDLS' => 'New Delhi'],
        ]),
    ]);

    $response = app(RailRadarClient::class)->stationsLookup();

    expect($response->json('data.NDLS'))->toBe('New Delhi');
});
