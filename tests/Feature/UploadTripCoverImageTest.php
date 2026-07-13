<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    Storage::fake('public');

    config([
        'integrations.trip_covers.enabled' => true,
        'integrations.trip_covers.driver' => 'rotating',
    ]);
});

test('users can upload a trip cover image', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'cover_image_exhausted' => true,
        'cover_image_version' => 2,
    ]);

    $this->actingAs($user)
        ->from(route('trips.show', $trip))
        ->post(route('trips.cover.upload', $trip), [
            'cover' => UploadedFile::fake()->image('shantiniketan.jpg', 1600, 900),
        ])
        ->assertRedirect(route('trips.show', $trip));

    $trip->refresh();

    expect($trip->cover_image_path)->not->toBeNull()
        ->and($trip->cover_image_source)->toBe('upload')
        ->and($trip->cover_image_exhausted)->toBeFalse()
        ->and($trip->cover_image_version)->toBe(3)
        ->and(Storage::disk('public')->exists((string) $trip->cover_image_path))->toBeTrue()
        ->and(Storage::disk('public')->exists((string) $trip->cover_image_thumb_path))->toBeTrue();
});

test('users cannot upload a cover for another users trip', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->create();

    $this->actingAs($other)
        ->post(route('trips.cover.upload', $trip), [
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ])
        ->assertForbidden();
});

test('users can upload a road trip cover image', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->road()->create([
        'cover_image_exhausted' => true,
        'cover_image_version' => 0,
    ]);

    $this->actingAs($user)
        ->from(route('road-trips.show', $trip))
        ->post(route('road-trips.cover.upload', $trip), [
            'cover' => UploadedFile::fake()->image('highway.jpg', 1920, 600),
        ])
        ->assertRedirect(route('road-trips.show', $trip));

    $trip->refresh();

    expect($trip->cover_image_path)->not->toBeNull()
        ->and($trip->cover_image_source)->toBe('upload')
        ->and($trip->cover_image_version)->toBe(1);
});
