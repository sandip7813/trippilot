<?php

use App\Enums\TravelStyle;
use App\Enums\TripType;
use App\Models\Trip;
use Tests\TestCase;

uses(TestCase::class);

test('material attribute changes are detected for trip planning fields', function () {
    $trip = new Trip;
    $trip->forceFill([
        'type' => TripType::Vacation,
        'travel_style' => TravelStyle::Family,
        'travelers' => 2,
        'destination' => [
            'label' => 'Goa, India',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
        ],
    ]);

    expect($trip->materialAttributesDiffer([
        'destination' => ['label' => 'Kerala, India'],
    ]))->toBeTrue()
        ->and($trip->materialAttributesDiffer([
            'title' => 'New title',
        ]))->toBeFalse();
});

test('generated itinerary detection works from stored days', function () {
    $trip = new Trip;
    $trip->forceFill([
        'itinerary' => Trip::emptyItinerary(),
    ]);

    expect($trip->hasGeneratedItinerary())->toBeFalse();

    $trip->itinerary = [
        'days' => [['day' => 1, 'title' => 'Day one', 'activities' => []]],
        'summary' => 'Plan',
        'packing_list' => [],
        'budget_breakdown' => [],
    ];

    expect($trip->hasGeneratedItinerary())->toBeTrue();
});
