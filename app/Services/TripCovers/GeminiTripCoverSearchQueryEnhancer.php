<?php

namespace App\Services\TripCovers;

use App\Services\Ai\Gemini\GeminiClient;
use Illuminate\Support\Facades\Log;

class GeminiTripCoverSearchQueryEnhancer
{
    public function __construct(
        private GeminiClient $client,
        private TripCoverPlacePhrase $placePhrase,
    ) {}

    /**
     * @param  array<string, mixed>|null  $destination
     * @return list<string>
     */
    public function queries(?array $destination, ?string $travelStyle): array
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return [];
        }

        if (! filter_var(config('integrations.trip_covers.use_gemini_prompt', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $place = $this->placePhrase->resolve($destination);

        if ($place === 'the destination') {
            return [];
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
                    'temperature' => 0.3,
                    'maxOutputTokens' => 120,
                ],
            ],
        );

        if ($response->failed()) {
            Log::warning('Gemini trip cover search query enhancement failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
            ]);

            return [];
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            return [];
        }

        return $this->parseQueries($text);
    }

    /**
     * @return list<string>
     */
    private function parseQueries(string $text): array
    {
        $lines = preg_split('/\R+/', trim($text)) ?: [];
        $queries = [];

        foreach ($lines as $line) {
            $query = trim(preg_replace('/^\d+[\).\s-]+/', '', trim($line)) ?? '');

            if ($query === '' || strlen($query) < 4) {
                continue;
            }

            $queries[] = mb_substr($query, 0, 80);

            if (count($queries) >= 4) {
                break;
            }
        }

        return $queries;
    }

    private function instruction(string $place, ?string $travelStyle): string
    {
        $style = $travelStyle !== null && $travelStyle !== ''
            ? "Travel style: {$travelStyle}."
            : '';

        return <<<PROMPT
Write exactly 3 short Unsplash photo search queries for a wide travel banner about {$place}.

Rules:
- Each line is one query (4–8 words), no numbering, no bullets, no explanation.
- Focus on what {$place} is FAMOUS FOR: iconic landmarks, temples, beaches, mountains, old towns, or signature landscapes tourists visit.
- Include the place name or a well-known local landmark name in every query.
- Prefer scenic/tourism subjects, not traffic, crowds, or generic city streets.
- Do not mention other countries or unrelated cities.
{$style}
PROMPT;
    }
}
