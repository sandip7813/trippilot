<?php

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Models\Trip;
use App\Services\Trips\TripCoverImageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('generate for trip if missing skips when cover already exists', function () {
    Storage::fake('public');

    $generator = Mockery::mock(TripCoverGenerator::class);
    $generator->shouldNotReceive('generate');

    app()->instance(TripCoverGenerator::class, $generator);

    $trip = new Trip;
    $trip->forceFill([
        'cover_image_path' => 'trip-covers/existing-banner.jpg',
    ]);

    $path = app(TripCoverImageService::class)->generateForTripIfMissing($trip);

    expect($path)->toBe('trip-covers/existing-banner.jpg');
});

test('cover destination candidates prefer waypoints over the return-leg destination', function () {
    $trip = new Trip;
    $trip->forceFill([
        'origin' => ['label' => 'Kolkata, WB, India', 'lat' => 22.5726, 'lng' => 88.3639, 'country_code' => 'in'],
        'destination' => ['label' => 'New Delhi, India', 'lat' => 28.6139, 'lng' => 77.2090, 'country_code' => 'in'],
        'waypoints' => [
            ['sequence' => 1, 'location' => ['label' => 'Agra, UP, India', 'lat' => 27.1753, 'lng' => 78.0098, 'country_code' => 'in']],
            ['sequence' => 2, 'location' => ['label' => 'Jaipur, RJ, India', 'lat' => 26.9155, 'lng' => 75.8190, 'country_code' => 'in']],
            ['sequence' => 3, 'location' => ['label' => 'New Delhi, India', 'lat' => 28.6139, 'lng' => 77.2090, 'country_code' => 'in']],
        ],
    ]);

    $labels = array_map(
        fn (array $location): string => $location['label'],
        $trip->coverDestinationCandidates(),
    );

    expect($labels)->toBe(['Agra, UP, India', 'Jaipur, RJ, India', 'New Delhi, India']);
});

test('cover destination candidates fall back to destination when there are no waypoints', function () {
    $trip = new Trip;
    $trip->forceFill([
        'origin' => ['label' => 'Kolkata, WB, India', 'lat' => 22.5726, 'lng' => 88.3639, 'country_code' => 'in'],
        'destination' => ['label' => 'Goa, India', 'lat' => 15.2993, 'lng' => 74.1240, 'country_code' => 'in'],
    ]);

    expect($trip->coverDestinationCandidates())->toBe([
        [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
            'place_id' => null,
            'country_code' => 'in',
        ],
    ]);
});
