<?php

use App\Services\Maps\Geoapify\GeoapifyPlacesService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('geoapify places maps category arrays from feature properties', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'api.geoapify.com/v2/places*' => Http::response([
            'features' => [[
                'geometry' => [
                    'coordinates' => [88.3638953, 22.5726459],
                ],
                'properties' => [
                    'name' => 'City Fuel Station',
                    'formatted' => 'City Fuel Station, Kolkata',
                    'place_id' => 'fuel-place-1',
                    'categories' => [
                        'service.vehicle.fuel',
                        'commercial',
                    ],
                ],
            ]],
        ]),
    ]);

    $places = app(GeoapifyPlacesService::class)->searchNearby(
        22.5726459,
        88.3638953,
        'service.vehicle.fuel',
        5,
    );

    expect($places)->toHaveCount(1)
        ->and($places[0]->name)->toBe('City Fuel Station')
        ->and($places[0]->category)->toBe('service.vehicle.fuel, commercial')
        ->and($places[0]->toArray()['category'])->toBe('service.vehicle.fuel, commercial');
});

test('geoapify places search accepts a custom radius', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'api.geoapify.com/v2/places*' => Http::response(['features' => []]),
    ]);

    app(GeoapifyPlacesService::class)->searchNearby(
        22.5726459,
        88.3638953,
        'accommodation.hotel',
        10,
        8000,
    );

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'filter=circle%3A')
            && str_contains($request->url(), '%2C8000');
    });
});

test('geoapify places falls back to requested category when none are returned', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => 'test-key']);

    Http::fake([
        'api.geoapify.com/v2/places*' => Http::response([
            'features' => [[
                'geometry' => [
                    'coordinates' => [88.3638953, 22.5726459],
                ],
                'properties' => [
                    'name' => 'Roadside Cafe',
                    'formatted' => 'Roadside Cafe, Kolkata',
                    'place_id' => 'cafe-place-1',
                ],
            ]],
        ]),
    ]);

    $places = app(GeoapifyPlacesService::class)->searchNearby(
        22.5726459,
        88.3638953,
        'catering.cafe',
        5,
    );

    expect($places[0]->category)->toBe('catering.cafe');
});
