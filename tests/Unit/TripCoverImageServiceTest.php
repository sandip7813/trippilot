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
