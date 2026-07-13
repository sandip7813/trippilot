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
    public function queries(?array $destination, ?string $searchPlace = null, ?string $travelStyle = null): array
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return [];
        }

        if (! filter_var(config('integrations.trip_covers.use_gemini_prompt', true), FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $place = $searchPlace ?? $this->placePhrase->resolve($destination);

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
                            ['text' => $this->instruction($place, $destination, $travelStyle)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 160,
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

            if (count($queries) >= 5) {
                break;
            }
        }

        return $queries;
    }

    /**
     * @param  array<string, mixed>|null  $destination
     */
    private function instruction(string $place, ?array $destination, ?string $travelStyle): string
    {
        $style = $travelStyle !== null && $travelStyle !== ''
            ? "Travel style: {$travelStyle}."
            : '';

        $fullLabel = trim((string) ($destination['label'] ?? $place));
        $alternatePlaces = array_values(array_filter(
            $this->placePhrase->searchPhrases($destination),
            static fn (string $phrase): bool => strcasecmp($phrase, $place) !== 0,
        ));
        $alternateHint = $alternatePlaces !== []
            ? 'Broader or nearby place names you may use in queries: '.implode('; ', array_slice($alternatePlaces, 0, 4)).'.'
            : '';

        return <<<PROMPT
Write exactly 4 short Unsplash photo search queries for a wide travel banner about {$place}.

Context: the traveller selected "{$fullLabel}" as the destination.
{$alternateHint}

Rules:
- Each line is one query (4–8 words), no numbering, no bullets, no explanation.
- Focus on iconic landmarks, architecture, landscapes, or tourism scenes for {$place}.
- If {$place} is a small town, university campus, or village with few stock photos, include at least one query using the nearest well-known city or district tourists associate with it (for example Shantiniketan → Bolpur or Visva Bharati campus).
- Prefer scenic subjects. Avoid portraits, headshots, random people, or unrelated celebrities.
- Include a recognizable place or landmark name in every query.
- Do not mention other countries or unrelated cities.
- Never use Bangladesh, Dhaka, Pakistan, or other neighbouring countries when the destination is in India.
{$style}
PROMPT;
    }
}
