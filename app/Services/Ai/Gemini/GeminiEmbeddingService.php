<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\Ai\EmbeddingService;
use App\Exceptions\AiGenerationException;
use App\Support\GeminiResponseErrors;

class GeminiEmbeddingService implements EmbeddingService
{
    public function __construct(private GeminiClient $client) {}

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            throw new AiGenerationException('Gemini API key is not configured.');
        }

        $normalized = trim($text);

        if ($normalized === '') {
            throw new AiGenerationException('Cannot embed empty text.');
        }

        $response = $this->client->embedContent($normalized);

        if ($response->failed()) {
            throw new AiGenerationException(
                GeminiResponseErrors::message($response, 'Unable to generate embedding. Please try again.'),
            );
        }

        $values = data_get($response->json(), 'embedding.values');

        if (! is_array($values) || $values === []) {
            throw new AiGenerationException('AI returned an empty embedding.');
        }

        return array_map(floatval(...), $values);
    }
}
