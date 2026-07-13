<?php

namespace App\Services\TripCovers;

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Data\TripCovers\TripCoverCandidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollinationsTripCoverGenerator implements TripCoverGenerator
{
    public function __construct(private TripCoverPromptBuilder $promptBuilder) {}

    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string
    {
        $candidate = $this->candidates($destination, $travelStyle, $width, $height)[0] ?? null;

        if ($candidate === null) {
            return null;
        }

        return $this->downloadCandidate($candidate, $destination);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    public function candidates(array $destination, ?string $travelStyle, int $width, int $height, int $offset = 0): array
    {
        $baseSeed = $this->promptBuilder->seed($destination);
        $candidates = [];

        for ($index = 0; $index < 3; $index++) {
            $seed = $baseSeed + $offset + $index;
            $imageUrl = $this->imageUrl($destination, $travelStyle, $width, $height, $seed);

            if ($imageUrl === null) {
                continue;
            }

            $candidates[] = new TripCoverCandidate(
                ref: 'pollinations:'.$seed,
                imageUrl: $imageUrl,
                attribution: [
                    'source' => 'AI generated',
                    'author' => null,
                    'license' => null,
                    'credit_url' => null,
                    'description' => $this->promptBuilder->build($destination, $travelStyle),
                ],
            );
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function downloadCandidate(TripCoverCandidate $candidate, array $destination): ?string
    {
        $response = Http::timeout(120)
            ->connectTimeout(15)
            ->retry([1000, 2000, 4000], 2, throw: false)
            ->get($candidate->imageUrl);

        if ($response->failed()) {
            Log::warning('Pollinations trip cover generation failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
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

    /**
     * @param  array<string, mixed>  $destination
     */
    private function imageUrl(array $destination, ?string $travelStyle, int $width, int $height, int $seed): ?string
    {
        $prompt = $this->promptBuilder->build($destination, $travelStyle);
        $baseUrl = rtrim((string) config('integrations.trip_covers.drivers.pollinations.base_url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        $query = http_build_query([
            'width' => $width,
            'height' => $height,
            'model' => (string) config('integrations.trip_covers.drivers.pollinations.model', 'flux'),
            'enhance' => config('integrations.trip_covers.drivers.pollinations.enhance', false) ? 'true' : 'false',
            'seed' => $seed,
        ]);

        return $baseUrl.'/'.rawurlencode($prompt).'?'.$query;
    }
}
