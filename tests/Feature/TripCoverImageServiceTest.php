<?php

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\TripCoverImageService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
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

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
        ],
    ]);

    $path = app(TripCoverImageService::class)->generateForTripIfMissing($trip);

    expect($path)->toBe("trip-covers/{$trip->id}-banner.jpg")
        ->and(Storage::disk('public')->exists("trip-covers/{$trip->id}-banner.jpg"))->toBeTrue()
        ->and(Storage::disk('public')->exists("trip-covers/{$trip->id}-thumb.jpg"))->toBeTrue();
});
