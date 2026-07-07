<?php

use App\Services\Ai\Gemini\GeminiTripCoverGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('gemini trip cover generator extracts inline image data', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'integrations.ai.drivers.gemini.image_model' => 'gemini-2.5-flash-image',
        'integrations.ai.drivers.gemini.image_enabled' => true,
    ]);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => 'image/png',
                                    'data' => base64_encode($pngBytes),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $bytes = app(GeminiTripCoverGenerator::class)->generate([
        'label' => 'Goa, India',
    ], 'Adventure', 1280, 520);

    expect($bytes)->not->toBeNull()
        ->and($bytes)->toBe($pngBytes);
});

test('gemini trip cover generator returns null when image generation is disabled', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.image_enabled' => false,
    ]);

    $bytes = app(GeminiTripCoverGenerator::class)->generate([
        'label' => 'Goa, India',
    ], null, 1280, 520);

    expect($bytes)->toBeNull();
});
