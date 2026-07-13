<?php

use App\Services\Trains\NearestRailheadResolver;
use Tests\TestCase;

uses(TestCase::class);

test('nearest railhead resolver maps shimla to kalka', function () {
    $railhead = app(NearestRailheadResolver::class)->forLocation([
        'label' => 'Shimla, Himachal Pradesh, India',
        'lat' => 31.1048,
        'lng' => 77.1734,
    ]);

    expect($railhead)->toMatchArray([
        'place_key' => 'shimla',
        'station' => [
            'code' => 'KLK',
            'name' => 'Kalka',
        ],
    ])->and($railhead['last_mile'])->toContain('Shimla');
});

test('nearest railhead resolver maps manali to chandigarh', function () {
    $railhead = app(NearestRailheadResolver::class)->forLocation([
        'label' => 'Manali, Himachal Pradesh, India',
        'lat' => 32.2432,
        'lng' => 77.1892,
    ]);

    expect($railhead['station']['code'])->toBe('CDG');
});

test('nearest railhead resolver returns null for regular cities', function () {
    $railhead = app(NearestRailheadResolver::class)->forLocation([
        'label' => 'Mumbai, India',
        'lat' => 19.076,
        'lng' => 72.8777,
    ]);

    expect($railhead)->toBeNull();
});
