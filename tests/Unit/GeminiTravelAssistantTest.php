<?php

use App\Data\Ai\AssistantChatResponse;
use App\Exceptions\AiGenerationException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiTravelAssistant;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('gemini travel assistant returns plain text responses', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'integrations.ai.drivers.gemini.model' => 'gemini-2.5-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'finishReason' => 'STOP',
                    'content' => [
                        'parts' => [
                            ['text' => 'October is ideal for Rajasthan before peak winter crowds.'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $assistant = new GeminiTravelAssistant(app(GeminiClient::class));

    $result = $assistant->chat('When should I visit Rajasthan?', [], [
        'user_name' => 'Test User',
    ]);

    expect($result)->toBeInstanceOf(AssistantChatResponse::class)
        ->and($result->message)->toContain('Rajasthan');
});

test('gemini travel assistant appends note when response is trimmed', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'integrations.ai.drivers.gemini.model' => 'gemini-2.5-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'finishReason' => 'MAX_TOKENS',
                    'content' => [
                        'parts' => [
                            ['text' => 'Day 1: Jaipur — Amber Fort, City Palace...'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $assistant = new GeminiTravelAssistant(app(GeminiClient::class));

    $result = $assistant->chat('Plan a 15-day Rajasthan trip', [], []);

    expect($result->message)->toContain('Day 1: Jaipur')
        ->and($result->message)->toContain('Response trimmed');
});

test('gemini travel assistant throws when api key is missing', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $assistant = new GeminiTravelAssistant(app(GeminiClient::class));

    $assistant->chat('Hello?', [], []);
})->throws(AiGenerationException::class, 'Gemini API key is not configured.');
