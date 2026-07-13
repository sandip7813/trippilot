<?php

use App\Enums\TripRouteMode;
use App\Models\Trip;
use App\Services\Trips\TripRouteResolver;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

function makeTrip(array $attributes = []): Trip
{
    $trip = new Trip([
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Jaipur, India',
            'lat' => 26.9124,
            'lng' => 75.7873,
            'country_code' => 'in',
        ],
        'route_mode' => TripRouteMode::MultiCity->value,
        'returns_to_origin' => true,
        'start_date' => Carbon::parse('2026-08-01'),
        'end_date' => Carbon::parse('2026-08-10'),
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
        ...$attributes,
    ]);

    $trip->syncOriginal();

    return $trip;
}

test('trip route resolver builds multi city travel legs with return leg', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip();

    $legs = $resolver->travelLegs($trip);

    expect($legs)->toHaveCount(4)
        ->and($legs[0]['from_label'])->toBe('Kolkata, India')
        ->and($legs[0]['to_label'])->toBe('Delhi, India')
        ->and($legs[0]['direction'])->toBe('outbound')
        ->and($legs[1]['direction'])->toBe('inter_city')
        ->and($legs[3]['direction'])->toBe('return')
        ->and($legs[3]['to_label'])->toBe('Kolkata, India');
});

test('trip route resolver includes return to origin in display points', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip();

    $summary = $resolver->summary($trip);

    expect($summary['route_display_points'])->toBe([
        'Kolkata, India',
        'Delhi, India',
        'Agra, India',
        'Jaipur, India',
        'Kolkata, India',
    ])->and($summary['route_label'])->toBe(
        'Kolkata, India → Delhi, India → Agra, India → Jaipur, India → Kolkata, India',
    );
});

test('trip route resolver builds route overview stops with stay dates and nights', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip();

    $stops = $resolver->routeOverviewStops($trip);

    expect($stops)->toHaveCount(5)
        ->and($stops[0])->toMatchArray([
            'kind' => 'origin',
            'label' => 'Kolkata, India',
            'departure_date' => '2026-08-01',
        ])
        ->and($stops[1])->toMatchArray([
            'kind' => 'stay',
            'label' => 'Delhi, India',
            'nights' => 3,
            'arrival_date' => '2026-08-01',
            'departure_date' => '2026-08-04',
        ])
        ->and($stops[2]['label'])->toBe('Agra, India')
        ->and($stops[2]['nights'])->toBe(1)
        ->and($stops[3]['label'])->toBe('Jaipur, India')
        ->and($stops[4])->toMatchArray([
            'kind' => 'return',
            'label' => 'Kolkata, India',
            'arrival_date' => '2026-08-10',
        ]);
});

test('trip route resolver lists every route city even when stay dates are unavailable', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip(['start_date' => null, 'end_date' => null]);

    $stops = $resolver->routeOverviewStops($trip);

    expect($stops)->toHaveCount(5)
        ->and(collect($stops)->pluck('label')->all())->toBe([
            'Kolkata, India',
            'Delhi, India',
            'Agra, India',
            'Jaipur, India',
            'Kolkata, India',
        ]);
});

test('trip route resolver includes waypoints even when route mode is simple', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip(['route_mode' => TripRouteMode::Simple->value]);

    expect($resolver->routePoints($trip))->toHaveCount(4)
        ->and(collect($resolver->routePoints($trip))->pluck('label')->all())->toBe([
            'Kolkata, India',
            'Delhi, India',
            'Agra, India',
            'Jaipur, India',
        ]);
});

test('trip route resolver assigns stay segments from waypoint nights', function () {
    $resolver = app(TripRouteResolver::class);
    $trip = makeTrip();

    $segments = $resolver->staySegments($trip);

    expect($segments)->toHaveCount(3)
        ->and($segments[0]['label'])->toBe('Delhi, India')
        ->and($segments[0]['date_from'])->toBe('2026-08-01')
        ->and($segments[0]['date_to'])->toBe('2026-08-03')
        ->and($segments[1]['date_from'])->toBe('2026-08-04');
});

test('simple trip still resolves outbound and return legs', function () {
    $resolver = app(TripRouteResolver::class);

    $trip = new Trip([
        'origin' => [
            'label' => 'Kolkata, India',
            'lat' => 22.5726,
            'lng' => 88.3639,
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Bolpur, India',
            'lat' => 23.2324,
            'lng' => 87.6810,
            'country_code' => 'in',
        ],
        'route_mode' => TripRouteMode::Simple->value,
        'returns_to_origin' => true,
        'start_date' => Carbon::parse('2026-08-01'),
        'end_date' => Carbon::parse('2026-08-04'),
        'waypoints' => [],
    ]);

    $trip->syncOriginal();

    expect($resolver->routeMode($trip))->toBe(TripRouteMode::Simple)
        ->and($resolver->travelLegs($trip))->toHaveCount(2)
        ->and($resolver->travelLegs($trip)[0]['direction'])->toBe('outbound')
        ->and($resolver->travelLegs($trip)[1]['direction'])->toBe('return');
});
