<?php

use App\Enums\TripScope;
use App\Models\Trip;
use Tests\TestCase;

uses(TestCase::class);

test('trip scope is domestic when origin and destination share a country', function () {
    expect(Trip::resolveTripScope(
        ['label' => 'Mumbai', 'country_code' => 'in'],
        ['label' => 'Goa', 'country_code' => 'in'],
    ))->toBe(TripScope::Domestic);
});

test('trip scope is international when origin and destination countries differ', function () {
    expect(Trip::resolveTripScope(
        ['label' => 'Mumbai', 'country_code' => 'in'],
        ['label' => 'Paris', 'country_code' => 'fr'],
    ))->toBe(TripScope::International);
});

test('trip scope assumes domestic indian travel when origin is missing', function () {
    expect(Trip::resolveTripScope(
        null,
        ['label' => 'Kerala', 'country_code' => 'in'],
    ))->toBe(TripScope::Domestic);
});

test('trip scope assumes international travel when origin is missing and destination is abroad', function () {
    expect(Trip::resolveTripScope(
        null,
        ['label' => 'Paris', 'country_code' => 'fr'],
    ))->toBe(TripScope::International);
});

test('trip scope is null when destination country is unknown', function () {
    expect(Trip::resolveTripScope(
        ['label' => 'Mumbai', 'country_code' => 'in'],
        ['label' => 'Somewhere'],
    ))->toBeNull();
});

test('normalize location stores lowercase country codes', function () {
    expect(Trip::normalizeLocation([
        'label' => 'Paris, France',
        'country_code' => 'FR',
    ]))->toMatchArray([
        'label' => 'Paris, France',
        'country_code' => 'fr',
    ]);
});

test('normalize location decodes json-encoded mongodb values', function () {
    expect(Trip::normalizeLocation('{"label":"Kolkata, WB, India","lat":22.5726459,"lng":88.3638953,"country_code":"in"}'))
        ->toMatchArray([
            'label' => 'Kolkata, WB, India',
            'lat' => 22.5726459,
            'lng' => 88.3638953,
            'country_code' => 'in',
        ]);
});
