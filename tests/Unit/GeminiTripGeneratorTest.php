<?php

use App\Data\Ai\GeneratedItinerary;
use App\Exceptions\AiGenerationException;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiTripGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('gemini trip generator parses structured json response', function () {
    config([
        'integrations.ai.drivers.gemini.api_key' => 'test-key',
        'integrations.ai.drivers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'integrations.ai.drivers.gemini.model' => 'gemini-2.5-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'title' => 'Paris Getaway',
                                    'summary' => 'A classic Paris itinerary.',
                                    'days' => [
                                        [
                                            'day' => 1,
                                            'date' => '2026-08-01',
                                            'title' => 'Left Bank',
                                            'activities' => [
                                                [
                                                    'time' => '10:00',
                                                    'title' => 'Visit Luxembourg Gardens',
                                                    'notes' => 'Bring a picnic.',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'budget' => [
                                        'estimated_total' => 2500,
                                    ],
                                    'packing_list' => ['Umbrella'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $generator = new GeminiTripGenerator(app(GeminiClient::class));

    $result = $generator->generate('', [
        'title' => 'Paris Getaway',
        'destination' => ['label' => 'Paris, France'],
        'day_count' => 1,
    ]);

    expect($result)->toBeInstanceOf(GeneratedItinerary::class)
        ->and($result->title)->toBe('Paris Getaway')
        ->and($result->days)->toHaveCount(1)
        ->and($result->packingList)->toContain('Umbrella');

    $stored = $result->toTripItinerary();

    expect($stored['summary'])->toBe('A classic Paris itinerary.')
        ->and($stored['packing_list'])->toContain('Umbrella');
});

test('gemini trip generator throws when api key is missing', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $generator = new GeminiTripGenerator(app(GeminiClient::class));

    $generator->generate('', []);
})->throws(AiGenerationException::class, 'Gemini API key is not configured.');
