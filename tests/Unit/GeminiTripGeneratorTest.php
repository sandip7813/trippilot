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
                                        'currency' => 'INR',
                                        'estimated_total' => 2500,
                                        'breakdown' => [
                                            'accommodation' => 1200,
                                            'food' => 500,
                                            'transport' => 300,
                                            'activities' => 400,
                                            'miscellaneous' => 100,
                                        ],
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
        ->and($stored['packing_list'])->toContain('Umbrella')
        ->and($stored['budget_breakdown']['currency'])->toBe('INR')
        ->and($stored['budget_breakdown']['breakdown'])->toMatchArray([
            'accommodation' => 1200.0,
            'food' => 500.0,
        ]);
});

test('gemini trip generator throws when api key is missing', function () {
    config(['integrations.ai.drivers.gemini.api_key' => null]);

    $generator = new GeminiTripGenerator(app(GeminiClient::class));

    $generator->generate('', []);
})->throws(AiGenerationException::class, 'Gemini API key is not configured.');

test('gemini trip generator uses fallback packing list when ai omits it', function () {
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
                                    'title' => 'Shimla Escape',
                                    'summary' => 'Hill station getaway.',
                                    'days' => [
                                        [
                                            'day' => 1,
                                            'date' => '2026-08-01',
                                            'title' => 'Mall Road',
                                            'activities' => [
                                                ['time' => '10:00', 'title' => 'Stroll Mall Road'],
                                            ],
                                        ],
                                    ],
                                    'budget' => [
                                        'currency' => 'INR',
                                        'estimated_total' => 15000,
                                        'breakdown' => [
                                            'accommodation' => 6000,
                                            'food' => 3000,
                                            'transport' => 2500,
                                            'activities' => 2500,
                                            'miscellaneous' => 1000,
                                        ],
                                    ],
                                    'packing_list' => [],
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
        'destination' => ['label' => 'Shimla, Himachal Pradesh, India'],
        'day_count' => 4,
        'travelers' => 2,
    ]);

    expect($result->packingList)->not->toBeEmpty()
        ->and($result->packingList[0])->toContain('Shimla');
});
