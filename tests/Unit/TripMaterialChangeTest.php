<?php

use App\Enums\TravelStyle;
use App\Enums\TripType;
use App\Models\Trip;
use Carbon\CarbonImmutable;
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

test('material attribute date comparison accepts immutable carbon dates', function () {
    $trip = new Trip;
    $trip->forceFill([
        'start_date' => CarbonImmutable::parse('2026-07-19'),
        'end_date' => CarbonImmutable::parse('2026-07-31'),
    ]);

    expect($trip->materialAttributesDiffer([
        'start_date' => '2026-07-19',
        'end_date' => '2026-07-31',
    ]))->toBeFalse()
        ->and($trip->materialAttributesDiffer([
            'end_date' => '2026-08-01',
        ]))->toBeTrue();
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
