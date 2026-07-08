<?php

namespace App\Services\Ai\Gemini;

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Services\TripCovers\TripCoverPromptBuilder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiTripCoverGenerator implements TripCoverGenerator
{
    public function __construct(
        private TripCoverPromptBuilder $promptBuilder,
    ) {}

    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string
    {
        if (! filled(config('integrations.ai.drivers.gemini.api_key'))) {
            return null;
        }

        if (! config('integrations.ai.drivers.gemini.image_enabled', true)) {
            return null;
        }

        $model = (string) config('integrations.ai.drivers.gemini.image_model', 'gemini-2.5-flash-image');
        $prompt = $this->promptBuilder->build($destination, $travelStyle);

        $response = $this->requestImage($model, $prompt, $this->aspectRatio($width, $height));

        if ($response->failed()) {
            $this->logFailure($model, $response);

            return null;
        }

        $imageBytes = $this->extractImageBytes($response);

        if ($imageBytes === null) {
            Log::warning('Gemini trip cover response did not include image data.', [
                'model' => $model,
                'response' => $response->json(),
            ]);
        }

        if ($imageBytes === null || $imageBytes === '') {
            return null;
        }

        return $imageBytes;
    }

    private function requestImage(string $model, string $prompt, string $aspectRatio): Response
    {
        return Http::baseUrl((string) config('integrations.ai.drivers.gemini.base_url'))
            ->timeout(120)
            ->connectTimeout(10)
            ->withQueryParameters([
                'key' => (string) config('integrations.ai.drivers.gemini.api_key'),
            ])
            ->retry([500, 1000, 2000], 2, throw: false)
            ->post("models/{$model}:generateContent", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => $aspectRatio,
                    ],
                ],
            ]);
    }

    private function aspectRatio(int $width, int $height): string
    {
        $ratio = $width / max($height, 1);

        if ($ratio >= 2.2) {
            return '21:9';
        }

        if ($ratio >= 1.6) {
            return '16:9';
        }

        if ($ratio >= 1.2) {
            return '4:3';
        }

        return '1:1';
    }

    private function logFailure(string $model, Response $response): void
    {
        $error = $response->json('error');
        $message = is_array($error) ? ($error['message'] ?? null) : null;
        $status = $response->status();

        Log::warning('Gemini trip cover generation failed.', [
            'model' => $model,
            'status' => $status,
            'message' => $message,
            'body' => $response->json(),
        ]);

        if ($status === 404) {
            Log::warning('Gemini image model not found. Update GEMINI_IMAGE_MODEL in .env (try gemini-2.5-flash-image).');
        }

        if ($status === 429) {
            Log::warning('Gemini image quota exceeded. Image generation may require billing on your Google AI project.');
        }
    }

    private function extractImageBytes(Response $response): ?string
    {
        /** @var list<array<string, mixed>> $parts */
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

            if (! is_array($inlineData)) {
                continue;
            }

            $encoded = $inlineData['data'] ?? null;

            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $decoded = base64_decode($encoded, true);

            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return null;
    }
}
