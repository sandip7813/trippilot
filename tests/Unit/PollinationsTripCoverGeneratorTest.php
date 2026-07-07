<?php

use App\Services\TripCovers\PollinationsTripCoverGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function puriDestination(): array
{
    return [
        'label' => 'Puri',
        'lat' => 19.8135,
        'lng' => 85.8312,
        'country_code' => 'in',
    ];
}

test('pollinations trip cover generator downloads image bytes', function () {
    config([
        'integrations.trip_covers.drivers.pollinations.base_url' => 'https://image.pollinations.ai/prompt',
        'integrations.trip_covers.drivers.pollinations.model' => 'flux',
        'integrations.trip_covers.drivers.pollinations.enhance' => false,
        'integrations.trip_covers.use_gemini_prompt' => false,
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'image.pollinations.ai/*' => Http::response($pngBytes, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $bytes = app(PollinationsTripCoverGenerator::class)->generate(puriDestination(), 'Adventure', 1280, 520);

    expect($bytes)->not->toBeNull()
        ->and($bytes)->toBe($pngBytes);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'image.pollinations.ai/prompt/')
            && str_contains($url, 'Puri, India')
            && ! str_contains($url, 'Taj Mahal')
            && $request['width'] === 1280
            && $request['height'] === 520
            && $request['model'] === 'flux'
            && $request['enhance'] === 'false'
            && isset($request['seed']);
    });
});

test('pollinations trip cover generator returns null for non-image responses', function () {
    config([
        'integrations.trip_covers.drivers.pollinations.base_url' => 'https://image.pollinations.ai/prompt',
        'integrations.trip_covers.use_gemini_prompt' => false,
    ]);

    Http::fake([
        'image.pollinations.ai/*' => Http::response('error', 200, [
            'Content-Type' => 'text/plain',
        ]),
    ]);

    $bytes = app(PollinationsTripCoverGenerator::class)->generate(puriDestination(), null, 640, 256);

    expect($bytes)->toBeNull();
});
