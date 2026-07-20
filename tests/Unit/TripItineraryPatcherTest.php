<?php

use App\Models\Trip;
use App\Support\Trips\TripItineraryPatcher;
use Tests\TestCase;

uses(TestCase::class);

test('it merges itinerary day patches by day number', function () {
    $trip = new Trip([
        'itinerary' => [
            'days' => [
                [
                    'day' => 1,
                    'title' => 'Arrival',
                    'activities' => [
                        ['time' => '10:00', 'title' => 'Check in'],
                    ],
                ],
                [
                    'day' => 2,
                    'title' => 'Explore',
                    'activities' => [],
                ],
            ],
            'summary' => 'Original summary',
            'packing_list' => ['Passport'],
            'budget_breakdown' => [],
        ],
    ]);

    $patcher = new TripItineraryPatcher;

    $result = $patcher->apply($trip, [
        'itinerary' => [
            'summary' => 'Updated summary',
            'days' => [
                [
                    'day' => 2,
                    'title' => 'Temple tour',
                    'activities' => [
                        ['time' => '09:00', 'title' => 'Visit temple'],
                    ],
                ],
                [
                    'day' => 3,
                    'title' => 'Departure',
                    'activities' => [],
                ],
            ],
            'packing_list' => ['Passport', 'Sunscreen'],
        ],
    ]);

    expect($result['itinerary']['summary'])->toBe('Updated summary')
        ->and($result['itinerary']['days'])->toHaveCount(3)
        ->and($result['itinerary']['days'][0]['title'])->toBe('Arrival')
        ->and($result['itinerary']['days'][1]['title'])->toBe('Temple tour')
        ->and($result['itinerary']['days'][1]['activities'][0]['title'])->toBe('Visit temple')
        ->and($result['itinerary']['days'][2]['title'])->toBe('Departure')
        ->and($result['itinerary']['packing_list'])->toBe(['Passport', 'Sunscreen']);
});

test('it applies notes patches separately from itinerary', function () {
    $trip = new Trip([
        'notes' => 'Old notes',
        'itinerary' => Trip::emptyItinerary(),
    ]);

    $patcher = new TripItineraryPatcher;

    $result = $patcher->apply($trip, [
        'notes' => 'Bring motion sickness tablets.',
    ]);

    expect($result['notes'])->toBe('Bring motion sickness tablets.')
        ->and($result['itinerary'])->toBeNull();
});
