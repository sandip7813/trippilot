<?php

use App\Models\Trip;
use App\Support\RoadTrip\RoadTripWaypointBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('road trip waypoint builder uses city waypoints from the route resolver', function () {
    $trip = new Trip([
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
        ],
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.124,
        ],
        'waypoints' => [
            [
                'sequence' => 1,
                'location' => [
                    'label' => 'Pune, India',
                    'lat' => 18.5204,
                    'lng' => 73.8567,
                ],
            ],
            [
                'sequence' => 2,
                'location' => [
                    'label' => 'Goa, India',
                    'lat' => 15.2993,
                    'lng' => 74.124,
                ],
            ],
        ],
        'route_mode' => 'multi_city',
        'returns_to_origin' => false,
    ]);

    $points = app(RoadTripWaypointBuilder::class)->build($trip);

    expect($points)->toHaveCount(3)
        ->and($points[0])->toMatchArray(['lat' => 19.076, 'lng' => 72.8777])
        ->and($points[1])->toMatchArray(['lat' => 18.5204, 'lng' => 73.8567])
        ->and($points[2])->toMatchArray(['lat' => 15.2993, 'lng' => 74.124]);
});

test('road trip waypoint builder inserts detour stops before the final city', function () {
    $trip = new Trip([
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
        ],
        'destination' => [
            'label' => 'Pune, India',
            'lat' => 18.5204,
            'lng' => 73.8567,
        ],
        'stops' => [[
            'label' => 'Scenic lookout',
            'lat' => 18.9,
            'lng' => 73.2,
        ]],
    ]);

    $points = app(RoadTripWaypointBuilder::class)->build($trip);

    expect($points)->toHaveCount(3)
        ->and($points[1])->toMatchArray(['lat' => 18.9, 'lng' => 73.2])
        ->and($points[2])->toMatchArray(['lat' => 18.5204, 'lng' => 73.8567]);
});

test('road trip waypoint builder appends return to origin when enabled', function () {
    $trip = new Trip([
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
        ],
        'destination' => [
            'label' => 'Pune, India',
            'lat' => 18.5204,
            'lng' => 73.8567,
        ],
        'waypoints' => [[
            'sequence' => 1,
            'location' => [
                'label' => 'Pune, India',
                'lat' => 18.5204,
                'lng' => 73.8567,
            ],
        ]],
        'route_mode' => 'multi_city',
        'returns_to_origin' => true,
    ]);

    $points = app(RoadTripWaypointBuilder::class)->build($trip);

    expect($points)->toHaveCount(3)
        ->and($points[2])->toMatchArray(['lat' => 19.076, 'lng' => 72.8777]);
});
