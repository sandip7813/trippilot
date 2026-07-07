<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function skipUnlessMongoDbAvailableForCovers(): void
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

function fakePollinationsCoverImage(): void
{
    config([
        'integrations.trip_covers.driver' => 'pollinations',
        'integrations.trip_covers.enabled' => true,
        'integrations.trip_covers.drivers.pollinations.base_url' => 'https://image.pollinations.ai/prompt',
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
    skipUnlessMongoDbAvailableForCovers();

    Trip::query()->whereNotNull('_id')->delete();
});

test('trip creation generates destination cover images', function () {
    fakePollinationsCoverImage();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('trips.store'), validTripPayloadForCover())
        ->assertRedirect();

    $trip = Trip::query()->where('user_id', $user->id)->latest()->first();

    expect($trip)->not->toBeNull()
        ->and($trip->cover_image_path)->toContain('-banner.jpg')
        ->and($trip->cover_image_thumb_path)->toContain('-thumb.jpg');
});

test('updating the destination regenerates cover images', function () {
    fakePollinationsCoverImage();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => [
            'label' => 'Tokyo, Japan',
            'lat' => 35.6762,
            'lng' => 139.6503,
            'place_id' => 'test-destination',
            'country_code' => 'jp',
        ],
        'cover_image_path' => 'trip-covers/old-banner.jpg',
        'cover_image_thumb_path' => 'trip-covers/old-thumb.jpg',
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

    $trip->refresh();

    expect($trip->cover_image_path)->toContain('-banner.jpg')
        ->and($trip->cover_image_thumb_path)->toContain('-thumb.jpg');
});

test('updating non-destination fields keeps existing cover paths', function () {
    fakePollinationsCoverImage();

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
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
