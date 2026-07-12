<?php

use App\Services\Maps\Geoapify\GeoapifyRoutingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('geoapify routing flattens multilinestring geometry into a polyline', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'api.geoapify.com/v1/routing*' => Http::response([
            'features' => [[
                'geometry' => [
                    'type' => 'MultiLineString',
                    'coordinates' => [
                        [
                            [72.8777, 19.076],
                            [73.0, 19.5],
                        ],
                        [
                            [73.0, 19.5],
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

    $route = app(GeoapifyRoutingService::class)->getRoute([
        ['lat' => 19.076, 'lng' => 72.8777],
        ['lat' => 18.5204, 'lng' => 73.8567],
    ]);

    expect($route->distanceKm)->toBe(150.0)
        ->and($route->durationSeconds)->toBe(7200)
        ->and($route->polyline)->toHaveCount(4)
        ->and($route->polyline[0])->toBe([19.076, 72.8777])
        ->and($route->polyline[3])->toBe([18.5204, 73.8567]);
});

test('geoapify routing supports linestring geometry', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'api.geoapify.com/v1/routing*' => Http::response([
            'features' => [[
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [72.8777, 19.076],
                        [73.8567, 18.5204],
                    ],
                ],
                'properties' => [
                    'distance' => 90000,
                    'time' => 3600,
                    'toll' => true,
                    'legs' => [],
                ],
            ]],
        ]),
    ]);

    $route = app(GeoapifyRoutingService::class)->getRoute([
        ['lat' => 19.076, 'lng' => 72.8777],
        ['lat' => 18.5204, 'lng' => 73.8567],
    ]);

    expect($route->polyline)->toHaveCount(2)
        ->and($route->hasTolls)->toBeTrue();
});
