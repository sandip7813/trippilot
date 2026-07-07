<?php

use App\Services\TripCovers\GeminiTripCoverSearchQueryEnhancer;
use App\Services\TripCovers\UnsplashTripCoverGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function unsplashPuriDestination(): array
{
    return [
        'label' => 'Puri',
        'lat' => 19.8135,
        'lng' => 85.8312,
        'country_code' => 'in',
    ];
}

/**
 * @return non-empty-string
 */
function unsplashTestJpegBytes(): string
{
    return base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=');
}

test('unsplash trip cover generator uses gemini landmark search queries first', function () {
    config([
        'integrations.trip_covers.drivers.unsplash.access_key' => 'test-access-key',
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.trip_covers.use_gemini_prompt' => true,
    ]);

    $jpegBytes = unsplashTestJpegBytes();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => "Jagannath Temple Puri Odisha\nPuri golden beach Bay of Bengal\nShree Jagannath Temple India",
                            ],
                        ],
                    ],
                ],
            ],
        ]),
        'api.unsplash.com/search/photos*' => function ($request) {
            $query = $request['query'];

            if ($query === 'Jagannath Temple Puri Odisha') {
                return Http::response([
                    'results' => [
                        [
                            'urls' => [
                                'regular' => 'https://images.unsplash.com/photo-jagannath',
                            ],
                            'links' => [
                                'download_location' => 'https://api.unsplash.com/photos/jagannath/download',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['results' => []], 200);
        },
        'api.unsplash.com/photos/jagannath/download' => Http::response([], 200),
        'images.unsplash.com/*' => Http::response($jpegBytes, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $bytes = app(UnsplashTripCoverGenerator::class)->generate(unsplashPuriDestination(), 'Adventure', 1920, 900);

    expect($bytes)->not->toBeNull()
        ->and($bytes)->toBe($jpegBytes);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.unsplash.com/search/photos')
            && $request['query'] === 'Jagannath Temple Puri Odisha';
    });
});

test('unsplash trip cover generator falls back to landmark queries without gemini', function () {
    config([
        'integrations.trip_covers.drivers.unsplash.access_key' => 'test-access-key',
        'integrations.trip_covers.use_gemini_prompt' => false,
    ]);

    $jpegBytes = unsplashTestJpegBytes();

    Http::fake([
        'api.unsplash.com/search/photos*' => function ($request) {
            if ($request['query'] === 'Puri, India landmark') {
                return Http::response([
                    'results' => [
                        [
                            'urls' => [
                                'regular' => 'https://images.unsplash.com/photo-landmark',
                            ],
                            'links' => [
                                'download_location' => 'https://api.unsplash.com/photos/landmark/download',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['results' => []], 200);
        },
        'api.unsplash.com/photos/landmark/download' => Http::response([], 200),
        'images.unsplash.com/*' => Http::response($jpegBytes, 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $bytes = app(UnsplashTripCoverGenerator::class)->generate(unsplashPuriDestination(), null, 640, 256);

    expect($bytes)->not->toBeNull();
});

test('unsplash trip cover generator returns null without access key', function () {
    config([
        'integrations.trip_covers.drivers.unsplash.access_key' => '',
    ]);

    Http::fake();

    $bytes = app(UnsplashTripCoverGenerator::class)->generate(unsplashPuriDestination(), null, 640, 256);

    expect($bytes)->toBeNull();

    Http::assertNothingSent();
});

test('unsplash trip cover generator returns null when search has no results', function () {
    config([
        'integrations.trip_covers.drivers.unsplash.access_key' => 'test-access-key',
        'integrations.trip_covers.use_gemini_prompt' => false,
    ]);

    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response(['results' => []], 200),
    ]);

    $bytes = app(UnsplashTripCoverGenerator::class)->generate(unsplashPuriDestination(), null, 640, 256);

    expect($bytes)->toBeNull();
});

test('gemini trip cover search query enhancer parses landmark queries', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.trip_covers.use_gemini_prompt' => true,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => "1. Jagannath Temple Puri Odisha\n2. Puri beach sunrise Bay of Bengal\n3. Konark Sun Temple near Puri",
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $queries = app(GeminiTripCoverSearchQueryEnhancer::class)->queries([
        'label' => 'Puri, Odisha, India',
        'country_code' => 'in',
    ], null);

    expect($queries)->toBe([
        'Jagannath Temple Puri Odisha',
        'Puri beach sunrise Bay of Bengal',
        'Konark Sun Temple near Puri',
    ]);
});
