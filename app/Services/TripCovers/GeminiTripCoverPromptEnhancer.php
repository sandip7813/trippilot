<?php

namespace App\Services\TripCovers;

use App\Services\Ai\Gemini\GeminiClient;
use Illuminate\Support\Facades\Log;

class GeminiTripCoverPromptEnhancer
{
    public function __construct(
        private GeminiClient $client,
        private TripCoverPlacePhrase $placePhrase,
    ) {}

    /**
     * @param  array<string, mixed>|null  $destination
     */
    public function enhance(?array $destination, ?string $travelStyle): ?string
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return null;
        }

        if (! filter_var(config('integrations.trip_covers.use_gemini_prompt', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $place = $this->placePhrase->resolve($destination);

        if ($place === 'the destination') {
            return null;
        }

        $response = $this->client->post(
            $this->client->model(),
            'generateContent',
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->instruction($place, $travelStyle)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 220,
                ],
            ],
        );

        if ($response->failed()) {
            Log::warning('Gemini trip cover prompt enhancement failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
            ]);

            return null;
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            return null;
        }

        $prompt = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return strlen($prompt) >= 40 ? $prompt : null;
    }

    private function instruction(string $place, ?string $travelStyle): string
    {
        $style = $travelStyle !== null && $travelStyle !== ''
            ? "Travel style: {$travelStyle}."
            : '';

        return <<<PROMPT
Write one image-generation prompt (single paragraph, max 80 words) for a photorealistic wide travel banner set in {$place}.

Rules:
- Describe ONLY scenery, beaches, rivers, streets, temples, or architecture that actually exist in {$place}.
- Name specific local features when you know them (for example Jagannath Temple and golden beach for Puri, Odisha).
- Do not mention or depict any other city or country.
- Do not include text, logos, watermarks, or close-up faces.
- Output ONLY the prompt text, nothing else.
{$style}
PROMPT;
    }
}
