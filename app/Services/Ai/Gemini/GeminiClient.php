<?php

namespace App\Services\Ai\Gemini;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $model, string $action, array $payload): Response
    {
        return $this->client()
            ->post("models/{$model}:{$action}", $payload);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(60)
            ->connectTimeout(10)
            ->withQueryParameters([
                'key' => $this->apiKey(),
            ])
            ->retry([200, 500, 1000], 2, throw: false);
    }

    private function baseUrl(): string
    {
        return config('integrations.ai.drivers.gemini.base_url');
    }

    private function apiKey(): string
    {
        return (string) config('integrations.ai.drivers.gemini.api_key');
    }

    public function model(): string
    {
        return (string) config('integrations.ai.drivers.gemini.model');
    }

    public function embeddingModel(): string
    {
        return (string) config('integrations.ai.drivers.gemini.embedding_model');
    }
}
