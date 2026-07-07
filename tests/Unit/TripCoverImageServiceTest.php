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

test('generate for trip if missing creates cover when absent', function () {
    Storage::fake('public');

    config([
        'integrations.trip_covers.driver' => 'pollinations',
        'integrations.trip_covers.enabled' => true,
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $generator = Mockery::mock(TripCoverGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->andReturn($pngBytes);

    app()->instance(TripCoverGenerator::class, $generator);

    $trip = new Trip;
    $trip->forceFill([
        '_id' => 'test-trip-id',
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
        ],
    ]);

    $path = app(TripCoverImageService::class)->generateForTripIfMissing($trip);

    expect($path)->toBe('trip-covers/test-trip-id-banner.jpg')
        ->and(Storage::disk('public')->exists('trip-covers/test-trip-id-banner.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('trip-covers/test-trip-id-thumb.jpg'))->toBeTrue();
});
