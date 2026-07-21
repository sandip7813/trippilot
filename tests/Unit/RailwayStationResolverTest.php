<?php

use App\Services\Trains\RailwayStationResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'integrations.trains.drivers.railradar.api_key' => 'rr_test_key',
        'integrations.trains.station_lookup_cache_ttl' => 3600,
    ]);

    Cache::forget('railradar:stations:lookup');
});

test('station resolver matches configured aliases for shantiniketan', function () {
    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'BHP' => 'Bolpur Shantiniketan',
                'BCT' => 'Mumbai Central',
            ],
        ]),
    ]);

    $station = app(RailwayStationResolver::class)->resolve([
        'label' => 'Shantiniketan, West Bengal, India',
        'lat' => 23.6813,
        'lng' => 87.6825,
    ]);

    expect($station)->toMatchArray([
        'code' => 'BHP',
        'name' => 'Bolpur Shantiniketan',
    ]);
});

test('station resolver matches station names from location labels', function () {
    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'NDLS' => 'New Delhi',
                'NZM' => 'Hazrat Nizamuddin',
            ],
        ]),
    ]);

    $station = app(RailwayStationResolver::class)->resolve([
        'label' => 'Delhi, India',
        'lat' => 28.6139,
        'lng' => 77.2090,
    ]);

    expect($station)->toMatchArray([
        'code' => 'NDLS',
        'name' => 'New Delhi',
    ]);
});

test('station resolver falls back to geoapify nearby train stations with valid categories', function () {
    config([
        'integrations.maps.drivers.geoapify.api_key' => 'geo_test_key',
    ]);

    Http::fake([
        'api.railradar.in/v1/lookup/stations' => Http::response([
            'success' => true,
            'data' => [
                'NDLS' => 'New Delhi',
            ],
        ]),
        'api.geoapify.com/v2/places*' => Http::response([
            'features' => [[
                'geometry' => [
                    'coordinates' => [77.2167, 28.6315],
                ],
                'properties' => [
                    'name' => 'New Delhi',
                    'place_id' => 'ndls-station',
                    'categories' => ['public_transport.train'],
                ],
            ]],
        ]),
    ]);

    $station = app(RailwayStationResolver::class)->resolve([
        'label' => 'Connaught Place, New Delhi, India',
        'lat' => 28.6315,
        'lng' => 77.2167,
    ]);

    expect($station)->toMatchArray([
        'code' => 'NDLS',
        'name' => 'New Delhi',
    ]);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'api.geoapify.com/v2/places')) {
            return false;
        }

        return str_contains($request->url(), 'categories=public_transport.train')
            && ! str_contains($request->url(), 'railway.rail');
    });
});

test('station resolver returns null when coordinates are missing', function () {
    Http::fake();

    $station = app(RailwayStationResolver::class)->resolve([
        'label' => 'Delhi, India',
        'lat' => null,
        'lng' => null,
    ]);

    expect($station)->toBeNull();

    Http::assertNothingSent();
});
