<?php

use App\Services\TripCovers\TripCoverPhotoValidator;
use Tests\TestCase;

uses(TestCase::class);

test('trip cover photo validator rejects photos mentioning dhaka for indian destinations', function () {
    $validator = app(TripCoverPhotoValidator::class);

    $destination = [
        'label' => 'Shantiniketan, West Bengal, India',
        'lat' => 23.6815,
        'lng' => 87.6826,
        'country_code' => 'in',
    ];

    $photo = [
        'description' => 'Modern building in Dhaka city Bangladesh',
        'alt_description' => 'Dhaka architecture skyline',
        'location' => [
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
        ],
    ];

    expect($validator->matchesDestination($photo, $destination, ['shantiniketan', 'bolpur']))
        ->toBeFalse();
});

test('trip cover photo validator accepts photos that mention the destination', function () {
    $validator = app(TripCoverPhotoValidator::class);

    $destination = [
        'label' => 'Shantiniketan, West Bengal, India',
        'country_code' => 'in',
    ];

    $photo = [
        'description' => 'Visva Bharati campus in Shantiniketan West Bengal',
        'alt_description' => 'Shantiniketan university architecture',
    ];

    expect($validator->matchesDestination($photo, $destination, ['shantiniketan', 'bolpur']))
        ->toBeTrue();
});

test('trip cover photo validator accepts nearby geotagged photos', function () {
    $validator = app(TripCoverPhotoValidator::class);

    $destination = [
        'label' => 'Shantiniketan, West Bengal, India',
        'lat' => 23.6815,
        'lng' => 87.6826,
        'country_code' => 'in',
    ];

    $photo = [
        'description' => 'Green fields near a small railway town',
        'alt_description' => 'Rural landscape',
        'location' => [
            'position' => [
                'latitude' => 23.6600,
                'longitude' => 87.7000,
            ],
            'country' => 'India',
        ],
    ];

    expect($validator->matchesDestination($photo, $destination, ['shantiniketan']))
        ->toBeTrue();
});
