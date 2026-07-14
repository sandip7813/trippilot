<?php

use App\Contracts\Maps\PlacesService;
use App\Data\Maps\PlaceResult;
use App\Enums\FuelType;
use App\Enums\TripType;
use App\Enums\VehicleClass;
use App\Models\Trip;
use App\Services\RoadTrips\RoadTripAmenitiesService;
use Tests\TestCase;

uses(TestCase::class);

test('bicycle trips hide motor vehicle amenity layers', function () {
    $trip = new Trip([
        'type' => TripType::Road,
        'road_profile' => [
            'vehicle_class' => VehicleClass::Bicycle->value,
            'fuel_type' => FuelType::None->value,
        ],
    ]);

    $service = app(RoadTripAmenitiesService::class);

    expect($service->layersForTrip($trip))->toBe([
        'food',
        'hotels',
        'toilets',
        'supermarkets',
        'atm',
        'pharmacy',
        'hospitals',
        'rest_areas',
        'emergency',
        'viewpoints',
        'bike',
    ]);
});

test('petrol car trips include fuel but not ev charging', function () {
    $trip = new Trip([
        'type' => TripType::Road,
        'road_profile' => [
            'vehicle_class' => VehicleClass::Car->value,
            'fuel_type' => FuelType::Petrol->value,
        ],
    ]);

    $layers = app(RoadTripAmenitiesService::class)->layersForTrip($trip);

    expect($layers)->toContain(
        'fuel',
        'parking',
        'toilets',
        'mechanics',
        'tyres',
        'atm',
        'hospitals',
        'supermarkets',
        'rest_areas',
        'emergency',
    )->and($layers)->not->toContain('ev', 'bike', 'car_wash');
});

test('ev car trips include charging stations', function () {
    $trip = new Trip([
        'type' => TripType::Road,
        'road_profile' => [
            'vehicle_class' => VehicleClass::Car->value,
            'fuel_type' => FuelType::Ev->value,
        ],
    ]);

    $layers = app(RoadTripAmenitiesService::class)->layersForTrip($trip);

    expect($layers)->toContain(
        'ev',
        'parking',
        'toilets',
        'mechanics',
        'tyres',
        'atm',
        'hospitals',
        'supermarkets',
        'rest_areas',
        'emergency',
    )->and($layers)->not->toContain('fuel', 'bike', 'car_wash');
});

test('hotel route sampling skips the origin portion of the route', function () {
    $trip = new Trip([
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
        ],
        'destination' => [
            'label' => 'Bolpur, India',
            'lat' => 23.6611,
            'lng' => 87.6962,
        ],
        'route' => [
            'distance_km' => 157.6,
            'polyline' => array_map(
                fn (int $index): array => [22.57 + ($index * 0.01), 88.36 - ($index * 0.008)],
                range(0, 99),
            ),
        ],
    ]);

    $method = new ReflectionMethod(RoadTripAmenitiesService::class, 'sampleRoutePoints');
    $method->setAccessible(true);
    $service = app(RoadTripAmenitiesService::class);

    /** @var list<array{lat: float, lng: float, zone?: string}> $hotelPoints */
    $hotelPoints = $method->invoke($service, $trip, 'hotels');

    /** @var list<array{lat: float, lng: float}> $fuelPoints */
    $fuelPoints = $method->invoke($service, $trip, 'fuel');

    expect($hotelPoints)->not->toBeEmpty()
        ->and($hotelPoints[0]['lat'])->toBeGreaterThan(22.58)
        ->and(collect($hotelPoints)->pluck('zone')->unique()->sort()->values()->all())
        ->toContain('en_route', 'destination')
        ->and($fuelPoints[0]['lat'])->toBe(22.57);
});

test('hotels near the origin are excluded after fetching', function () {
    $trip = new Trip([
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
        ],
        'destination' => [
            'label' => 'Bolpur, India',
            'lat' => 23.6611,
            'lng' => 87.6962,
        ],
        'route' => [
            'distance_km' => 157.6,
            'polyline' => [[22.5726, 88.3639], [23.6611, 87.6962]],
        ],
    ]);

    $method = new ReflectionMethod(RoadTripAmenitiesService::class, 'excludeHotelsNearOrigin');
    $method->setAccessible(true);
    $service = app(RoadTripAmenitiesService::class);

    $filtered = $method->invoke($service, $trip, [
        [
            'name' => 'Kolkata Hotel',
            'lat' => 22.58,
            'lng' => 88.37,
            'route_zone' => 'en_route',
        ],
        [
            'name' => 'Highway Hotel',
            'lat' => 23.2,
            'lng' => 88.0,
            'route_zone' => 'en_route',
        ],
        [
            'name' => 'Bolpur Hotel',
            'lat' => 23.6611,
            'lng' => 87.6962,
            'route_zone' => 'destination',
        ],
    ]);

    expect(collect($filtered)->pluck('name')->all())
        ->toBe(['Highway Hotel', 'Bolpur Hotel']);
});

test('hotels are fetched with a wider search radius along the route', function () {
    $placesService = Mockery::mock(PlacesService::class);
    $placesService->shouldReceive('searchNearPoint')
        ->atLeast()
        ->once()
        ->withArgs(function (float $lat, float $lng, array $categories, int $limit, int $radius): bool {
            return $limit === 10
                && $radius === 8000
                && in_array('accommodation.hotel', $categories, true);
        })
        ->andReturn([
            new PlaceResult(
                name: 'Highway Hotel',
                category: 'accommodation.hotel',
                latitude: 23.2,
                longitude: 88.0,
            ),
        ]);

    app()->instance(PlacesService::class, $placesService);

    $trip = new Trip([
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
        ],
        'destination' => [
            'label' => 'Bolpur, India',
            'lat' => 23.6611,
            'lng' => 87.6962,
        ],
        'route' => [
            'distance_km' => 157.6,
            'polyline' => array_map(
                fn (int $index): array => [22.57 + ($index * 0.01), 88.36 - ($index * 0.008)],
                range(0, 99),
            ),
        ],
    ]);

    $hotels = app(RoadTripAmenitiesService::class)->fetchForTrip($trip, 'hotels');

    expect($hotels)->not->toBeEmpty()
        ->and($hotels[0]['name'])->toBe('Highway Hotel');
});

test('road trip amenity layers use supported geoapify categories', function () {
    expect(RoadTripAmenitiesService::LAYER_CATEGORIES)->toMatchArray([
        'tyres' => 'commercial.vehicle',
        'rest_areas' => 'leisure.picnic,highway.motorway.junction',
        'emergency' => 'emergency,service.police,service.fire_station',
        'viewpoints' => 'tourism.attraction.viewpoint,tourism.attraction',
    ]);
});

test('non-hotel amenity layers batch categories into one places search per sample point', function () {
    $placesService = Mockery::mock(PlacesService::class);
    $placesService->shouldReceive('searchNearby')
        ->twice()
        ->withArgs(function (float $lat, float $lng, string $categories, int $limit, int $radius): bool {
            return $categories === 'leisure.picnic,highway.motorway.junction'
                && $limit === 5
                && $radius === 2000;
        })
        ->andReturn([
            new PlaceResult(
                name: 'Highway Rest Stop',
                category: 'leisure.picnic',
                latitude: 23.0,
                longitude: 88.1,
            ),
        ]);

    app()->instance(PlacesService::class, $placesService);

    $trip = new Trip([
        'route' => [
            'distance_km' => 120,
            'polyline' => [[22.5726, 88.3639], [23.6611, 87.6962]],
        ],
    ]);

    $restAreas = app(RoadTripAmenitiesService::class)->fetchForTrip($trip, 'rest_areas');

    expect($restAreas)->toHaveCount(1)
        ->and($restAreas[0]['name'])->toBe('Highway Rest Stop');
});
