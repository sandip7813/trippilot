<?php

namespace App\Services\TripCovers;

use App\Contracts\TripCovers\TripCoverGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollinationsTripCoverGenerator implements TripCoverGenerator
{
    public function __construct(private TripCoverPromptBuilder $promptBuilder) {}

    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string
    {
        $prompt = $this->promptBuilder->build($destination, $travelStyle);
        $baseUrl = rtrim((string) config('integrations.trip_covers.drivers.pollinations.base_url'), '/');

        $response = Http::timeout(120)
            ->connectTimeout(15)
            ->retry([1000, 2000, 4000], 2, throw: false)
            ->get($baseUrl.'/'.rawurlencode($prompt), [
                'width' => $width,
                'height' => $height,
                'model' => (string) config('integrations.trip_covers.drivers.pollinations.model', 'flux'),
                'enhance' => config('integrations.trip_covers.drivers.pollinations.enhance', false) ? 'true' : 'false',
                'seed' => $this->promptBuilder->seed($destination),
            ]);

        if ($response->failed()) {
            Log::warning('Pollinations trip cover generation failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
                'width' => $width,
                'height' => $height,
            ]);

            return null;
        }

        $contentType = (string) $response->header('Content-Type');

        if (! str_starts_with($contentType, 'image/')) {
            Log::warning('Pollinations trip cover response was not an image.', [
                'content_type' => $contentType,
                'destination' => $destination['label'] ?? null,
            ]);

            return null;
        }

        $bytes = $response->body();

        return $bytes !== '' ? $bytes : null;
    }
}
