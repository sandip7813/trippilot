<?php

use App\Services\TripCovers\GeminiTripCoverPromptEnhancer;
use App\Services\TripCovers\TripCoverPromptBuilder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['integrations.trip_covers.use_gemini_prompt' => false]);
});

test('trip cover prompt expands short indian city names with country context', function () {
    $prompt = app(TripCoverPromptBuilder::class)->build([
        'label' => 'Puri',
        'country_code' => 'in',
    ], null);

    expect($prompt)
        ->toContain('Puri, India')
        ->toContain('authentic local environment')
        ->not->toContain('Taj Mahal')
        ->not->toContain('Eiffel Tower');
});

test('trip cover prompt keeps full autocomplete labels intact', function () {
    $prompt = app(TripCoverPromptBuilder::class)->build([
        'label' => 'Puri, Odisha, India',
        'country_code' => 'in',
    ], 'Family');

    expect($prompt)
        ->toContain('Puri, Odisha, India')
        ->not->toContain('Puri, Odisha, India, India')
        ->toContain('Family');
});

test('trip cover prompt seed is stable for the same destination', function () {
    $builder = app(TripCoverPromptBuilder::class);

    $destination = ['label' => 'Puri', 'country_code' => 'in'];

    expect($builder->seed($destination))->toBe($builder->seed($destination));
});

test('gemini trip cover prompt enhancer returns location specific prompt', function () {
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
                                'text' => 'Golden sunrise over Puri beach on the Bay of Bengal with Jagannath Temple shikhara in soft focus, Odisha India, photorealistic wide travel banner.',
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $prompt = app(GeminiTripCoverPromptEnhancer::class)->enhance([
        'label' => 'Puri, Odisha, India',
        'country_code' => 'in',
    ], null);

    expect($prompt)
        ->toContain('Puri')
        ->toContain('Jagannath');
});
