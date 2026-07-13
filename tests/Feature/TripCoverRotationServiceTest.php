<?php

use App\Models\Trip;
use App\Models\User;
use App\Services\TripCovers\TripCoverRotationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
});

test('trip cover rotation prefers wikipedia on first attempt', function () {
    config([
        'integrations.trip_covers.driver' => 'rotating',
        'integrations.trip_covers.pollinations_fallback' => false,
        'integrations.trip_covers.drivers.unsplash.access_key' => '',
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'en.wikipedia.org/*' => Http::response([
            'title' => 'Shantiniketan',
            'description' => 'Neighbourhood in Bolpur, India',
            'originalimage' => [
                'source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/example/shantiniketan.jpg',
            ],
            'content_urls' => [
                'desktop' => [
                    'page' => 'https://en.wikipedia.org/wiki/Shantiniketan',
                ],
            ],
        ]),
        'upload.wikimedia.org/*' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/png',
        ]),
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'destination' => [
            'label' => 'Shantiniketan, West Bengal, India',
            'lat' => 23.6815,
            'lng' => 87.6826,
            'country_code' => 'in',
        ],
    ]);

    $result = app(TripCoverRotationService::class)->resolve(
        $trip,
        $trip->destination,
        null,
        1920,
        900,
        tryNextSource: false,
    );

    expect($result)->not->toBeNull()
        ->and($result->source->value)->toBe('wikipedia')
        ->and($result->bytes)->toBe($pngBytes);
});

test('trip cover rotation advances to next source when user tries another photo', function () {
    config([
        'integrations.trip_covers.driver' => 'rotating',
        'integrations.trip_covers.pollinations_fallback' => false,
        'integrations.trip_covers.drivers.unsplash.access_key' => '',
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'en.wikipedia.org/*' => Http::response([], 404),
        'commons.wikimedia.org/*' => Http::response([
            'query' => [
                'pages' => [
                    '1' => [
                        'title' => 'File:Bolpur station.jpg',
                        'imageinfo' => [[
                            'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/example/bolpur.jpg',
                            'url' => 'https://upload.wikimedia.org/wikipedia/commons/example/bolpur.jpg',
                            'mime' => 'image/jpeg',
                        ]],
                    ],
                ],
            ],
        ]),
        'upload.wikimedia.org/*' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'cover_image_source' => 'wikipedia',
        'cover_image_source_index' => 0,
        'cover_image_tried_refs' => ['wikipedia:Shantiniketan'],
        'destination' => [
            'label' => 'Shantiniketan, West Bengal, India',
            'lat' => 23.6815,
            'lng' => 87.6826,
            'country_code' => 'in',
        ],
    ]);

    $result = app(TripCoverRotationService::class)->resolve(
        $trip,
        $trip->destination,
        null,
        1920,
        900,
        tryNextSource: true,
    );

    expect($result)->not->toBeNull()
        ->and($result->source->value)->toBe('commons_geo');
});

test('trip cover rotation tries the next candidate within the same source', function () {
    config([
        'integrations.trip_covers.driver' => 'rotating',
        'integrations.trip_covers.pollinations_fallback' => false,
        'integrations.trip_covers.drivers.unsplash.access_key' => '',
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $secondBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAD0lEQVR42mP8z8B/DwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'en.wikipedia.org/*' => Http::response([], 404),
        'commons.wikimedia.org/*' => Http::response([
            'query' => [
                'pages' => [
                    '1' => [
                        'title' => 'File:Shantiniketan campus first view.jpg',
                        'imageinfo' => [[
                            'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/example/first.jpg',
                            'mime' => 'image/jpeg',
                        ]],
                    ],
                    '2' => [
                        'title' => 'File:Shantiniketan campus second view.jpg',
                        'imageinfo' => [[
                            'thumburl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/example/second.jpg',
                            'mime' => 'image/jpeg',
                        ]],
                    ],
                ],
            ],
        ]),
        'upload.wikimedia.org/wikipedia/commons/thumb/example/first.jpg' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
        'upload.wikimedia.org/wikipedia/commons/thumb/example/second.jpg' => Http::response($secondBytes, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'cover_image_source' => 'commons_geo',
        'cover_image_source_index' => 1,
        'cover_image_tried_refs' => ['commons_geo:File:Shantiniketan campus first view.jpg'],
        'destination' => [
            'label' => 'Shantiniketan, West Bengal, India',
            'lat' => 23.6815,
            'lng' => 87.6826,
            'country_code' => 'in',
        ],
    ]);

    $result = app(TripCoverRotationService::class)->resolve(
        $trip,
        $trip->destination,
        null,
        1920,
        900,
        tryNextSource: true,
    );

    expect($result)->not->toBeNull()
        ->and($result->source->value)->toBe('commons_geo')
        ->and($result->ref)->toBe('commons_geo:File:Shantiniketan campus second view.jpg')
        ->and($result->bytes)->toBe($secondBytes);
});

test('trip cover rotation skips legacy unsplash covers when trying another photo', function () {
    config([
        'integrations.trip_covers.driver' => 'rotating',
        'integrations.trip_covers.pollinations_fallback' => false,
        'integrations.trip_covers.drivers.unsplash.access_key' => '',
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'en.wikipedia.org/*' => Http::response([
            'title' => 'Shantiniketan',
            'description' => 'Neighbourhood in Bolpur, India',
            'originalimage' => [
                'source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/example/shantiniketan.jpg',
            ],
            'content_urls' => [
                'desktop' => [
                    'page' => 'https://en.wikipedia.org/wiki/Shantiniketan',
                ],
            ],
        ]),
        'upload.wikimedia.org/*' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/png',
        ]),
        'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
    ]);

    $user = User::factory()->create();
    $trip = Trip::factory()->forUser($user)->create([
        'cover_image_path' => 'trip-covers/existing-banner.jpg',
        'cover_image_source' => 'unsplash',
        'cover_image_source_index' => 0,
        'cover_image_tried_refs' => [],
        'destination' => [
            'label' => 'Shantiniketan, West Bengal, India',
            'lat' => 23.6815,
            'lng' => 87.6826,
            'country_code' => 'in',
        ],
    ]);

    $result = app(TripCoverRotationService::class)->resolve(
        $trip,
        $trip->destination,
        null,
        1920,
        900,
        tryNextSource: true,
    );

    expect($result)->not->toBeNull()
        ->and($result->source->value)->toBe('wikipedia')
        ->and($result->triedRefs)->toContain('unsplash:legacy');
});
