<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class GeminiResponseErrors
{
    public static function message(Response $response, string $fallback): string
    {
        $status = $response->status();
        $apiMessage = strtolower((string) data_get($response->json(), 'error.message', ''));
        $statusLabel = (string) data_get($response->json(), 'error.status', '');

        if ($status === 429 || $statusLabel === 'RESOURCE_EXHAUSTED' || str_contains($apiMessage, 'quota')) {
            return 'Gemini API daily limit reached on the free tier. Please wait about an hour and try again, or upgrade your API plan.';
        }

        if ($status === 404 || str_contains($apiMessage, 'not found')) {
            return 'Gemini embedding model is invalid or unavailable. Set GEMINI_EMBEDDING_MODEL to gemini-embedding-001 in your environment.';
        }

        if ($status === 401 || $status === 403) {
            return 'Gemini API key is invalid or not authorized. Check GEMINI_API_KEY in your environment.';
        }

        if ($status === 503 || $statusLabel === 'UNAVAILABLE') {
            return 'Gemini is temporarily unavailable. Please try again shortly.';
        }

        Log::warning('Gemini API request failed.', [
            'status' => $status,
            'error' => $response->json('error'),
        ]);

        return $fallback;
    }
}
