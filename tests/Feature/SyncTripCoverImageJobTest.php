<?php

use App\Jobs\SyncTripCoverImageJob;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\TripCoverImageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function skipUnlessMongoDbAvailableForCoverJob(): void
{
    if (! extension_loaded('mongodb')) {
        test()->markTestSkipped('MongoDB PHP extension is not installed.');
    }

    try {
        Trip::query()->where('_id', '!=', null)->limit(1)->get();
    } catch (Throwable $exception) {
        test()->markTestSkipped('MongoDB is not available: '.$exception->getMessage());
    }
}

function fakePollinationsCoverImageForJob(): void
{
    config([
        'integrations.trip_covers.driver' => 'pollinations',
        'integrations.trip_covers.enabled' => true,
        'integrations.trip_covers.drivers.pollinations.base_url' => 'https://image.pollinations.ai/prompt',
        'integrations.trip_covers.use_gemini_prompt' => false,
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'image.pollinations.ai/*' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);
}

/**
 * @return array<string, mixed>
 */
function validTripPayloadForCover(array $overrides = []): array
{
    return array_merge([
        'title' => 'Tokyo Adventure',
        'origin' => [
            'label' => 'Mumbai, India',
            'lat' => 19.076,
            'lng' => 72.8777,
            'place_id' => 'test-origin',
            'country_code' => 'in',
        ],
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => 35.6762,
            'lng' => 139.6503,
            'place_id' => 'test-destination',
            'country_code' => 'jp',
        ],
        'type' => 'vacation',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeeks(2)->toDateString(),
        'budget' => 3000,
        'travelers' => 2,
        'notes' => 'Cherry blossom season',
    ], $overrides);
}

beforeEach(function () {
    skipUnlessMongoDbAvailableForCoverJob();

    Trip::query()->whereNotNull('_id')->delete();
});

test('trip creation queues destination cover generation', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayloadForCover())
        ->assertRedirect();

    Queue::assertPushed(SyncTripCoverImageJob::class, function (SyncTripCoverImageJob $job): bool {
        return $job->onlyIfMissing === false;
    });
});

test('updating the destination queues cover regeneration', function () {
    Queue::fake();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => 35.6762,
            'lng' => 139.6503,
            'place_id' => 'test-destination',
            'country_code' => 'jp',
        ],
    ]);

    $this->actingAs($user)
        ->put(route('trips.update', $trip), validTripPayloadForCover([
            'destination' => [
                'label' => 'Kyoto, Japan',
                'lat' => 35.0116,
                'lng' => 135.7681,
                'place_id' => 'test-kyoto',
                'country_code' => 'jp',
            ],
        ]))
        ->assertRedirect(route('trips.show', $trip));

    Queue::assertPushed(SyncTripCoverImageJob::class);
});

test('sync trip cover image job stores cover images', function () {
    fakePollinationsCoverImageForJob();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => [
            'label' => 'Shimla, Himachal Pradesh, India',
            'lat' => 31.1048,
            'lng' => 77.1734,
            'place_id' => 'test-shimla',
            'country_code' => 'in',
        ],
    ]);

    (new SyncTripCoverImageJob((string) $trip->id))->handle(app(TripCoverImageService::class));

    $trip->refresh();

    expect($trip->cover_image_path)->toBeString()->toContain('-banner.jpg')
        ->and($trip->cover_image_thumb_path)->toBeString()->toContain('-thumb.jpg');
});

test('sync trip cover image job skips when cover already exists and only if missing', function () {
    fakePollinationsCoverImageForJob();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'cover_image_path' => 'trip-covers/existing-banner.jpg',
        'cover_image_thumb_path' => 'trip-covers/existing-thumb.jpg',
    ]);

    (new SyncTripCoverImageJob((string) $trip->id, onlyIfMissing: true))
        ->handle(app(TripCoverImageService::class));

    $trip->refresh();

    expect($trip->cover_image_path)->toBe('trip-covers/existing-banner.jpg');

    Http::assertNothingSent();
});

test('updating non-destination fields keeps existing cover paths', function () {
    fakePollinationsCoverImageForJob();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => validTripPayloadForCover()['destination'],
        'cover_image_path' => 'trip-covers/existing-banner.jpg',
        'cover_image_thumb_path' => 'trip-covers/existing-thumb.jpg',
    ]);

    $this->actingAs($user)
        ->put(route('trips.update', $trip), validTripPayloadForCover([
            'title' => 'Renamed Trip',
        ]))
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->title)->toBe('Renamed Trip')
        ->and($trip->cover_image_path)->toBe('trip-covers/existing-banner.jpg')
        ->and($trip->cover_image_thumb_path)->toBe('trip-covers/existing-thumb.jpg');

    Http::assertNothingSent();
});
