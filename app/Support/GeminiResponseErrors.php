<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class GeminiResponseErrors
{
    /**
     * Turn a failed Gemini response into a message that is safe to show end
     * users. Provider names and configuration details only go to the log.
     */
    public static function message(Response $response, string $fallback): string
    {
        $status = $response->status();
        $apiMessage = strtolower((string) data_get($response->json(), 'error.message', ''));
        $statusLabel = (string) data_get($response->json(), 'error.status', '');

        Log::warning('Gemini API request failed.', [
            'status' => $status,
            'error' => $response->json('error'),
        ]);

        if ($status === 429 || $statusLabel === 'RESOURCE_EXHAUSTED' || str_contains($apiMessage, 'quota')) {
            return 'The daily limit for AI features has been reached. Please wait about an hour and try again.';
        }

        if ($status === 404 || $status === 401 || $status === 403 || str_contains($apiMessage, 'not found')) {
            return 'AI features are not available right now. Please try again later.';
        }

        if ($status === 503 || $statusLabel === 'UNAVAILABLE') {
            return 'AI features are temporarily unavailable. Please try again shortly.';
        }

        return $fallback;
    }
}
