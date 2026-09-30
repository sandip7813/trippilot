<?php

use App\Exceptions\AiGenerationException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiTripGenerator;
use App\Support\GeminiResponseErrors;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('gemini response errors explains quota exhaustion', function () {
    $response = new Response(new GuzzleHttp\Psr7\Response(429, [], json_encode([
        'error' => [
            'code' => 429,
            'message' => 'You exceeded your current quota',
            'status' => 'RESOURCE_EXHAUSTED',
        ],
    ])));

    expect(GeminiResponseErrors::message($response, 'fallback'))
        ->toContain('daily limit');
});

test('gemini trip generator surfaces quota errors to users', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'integrations.ai.drivers.gemini.model' => 'gemini-2.5-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 429,
                'message' => 'You exceeded your current quota',
                'status' => 'RESOURCE_EXHAUSTED',
            ],
        ], 429),
    ]);

    $generator = new GeminiTripGenerator(app(GeminiClient::class));

    $generator->generate('', [
        'destination' => ['label' => 'Shimla, Himachal Pradesh, India'],
    ]);
})->throws(AiGenerationException::class, 'daily limit');

test('gemini response errors never expose the provider or configuration to users', function (int $status, string $apiMessage) {
    $response = new Response(new GuzzleHttp\Psr7\Response($status, [], json_encode([
        'error' => ['code' => $status, 'message' => $apiMessage],
    ])));

    expect(GeminiResponseErrors::message($response, 'fallback'))
        ->not->toContain('Gemini')
        ->not->toContain('API_KEY')
        ->not->toContain('environment');
})->with([
    'quota' => [429, 'You exceeded your current quota'],
    'bad key' => [403, 'API key not valid'],
    'missing model' => [404, 'models/x is not found'],
    'unavailable' => [503, 'The model is overloaded'],
]);
