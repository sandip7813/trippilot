<?php

namespace App\Services\TripCovers;

use App\Contracts\TripCovers\TripCoverGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashTripCoverGenerator implements TripCoverGenerator
{
    public function __construct(
        private TripCoverPlacePhrase $placePhrase,
        private GeminiTripCoverSearchQueryEnhancer $searchQueryEnhancer,
    ) {}

    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string
    {
        $accessKey = (string) config('integrations.trip_covers.drivers.unsplash.access_key');

        if ($accessKey === '') {
            Log::warning('Unsplash trip cover generation skipped because UNSPLASH_ACCESS_KEY is not configured.');

            return null;
        }

        $place = $this->placePhrase->resolve($destination);

        if ($place === 'the destination') {
            return null;
        }

        foreach ($this->searchQueries($destination, $place, $travelStyle) as $query) {
            $photo = $this->findPhoto($accessKey, $query);

            if ($photo !== null) {
                return $this->downloadPhoto($accessKey, $photo, $destination);
            }
        }

        Log::warning('Unsplash trip cover search returned no photos.', [
            'destination' => $destination['label'] ?? null,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<string>
     */
    private function searchQueries(array $destination, string $place, ?string $travelStyle): array
    {
        $queries = $this->searchQueryEnhancer->queries($destination, $travelStyle);

        $fallbacks = [
            "{$place} landmark",
            "{$place} tourism",
            $place,
        ];

        return array_values(array_unique(array_filter([...$queries, ...$fallbacks])));
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    private function downloadPhoto(string $accessKey, array $photo, array $destination): ?string
    {
        $this->triggerDownloadTracking($accessKey, $photo);

        $imageUrl = $photo['urls']['regular'] ?? $photo['urls']['full'] ?? null;

        if (! is_string($imageUrl) || $imageUrl === '') {
            return null;
        }

        $response = Http::timeout(60)->get($imageUrl);

        if ($response->failed()) {
            Log::warning('Unsplash trip cover download failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
            ]);

            return null;
        }

        $bytes = $response->body();

        return $bytes !== '' ? $bytes : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPhoto(string $accessKey, string $query): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => "Client-ID {$accessKey}",
            'Accept-Version' => 'v1',
        ])
            ->timeout(20)
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'orientation' => 'landscape',
                'content_filter' => 'high',
                'per_page' => 5,
            ]);

        if ($response->failed()) {
            Log::warning('Unsplash trip cover search failed.', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        }

        /** @var list<array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return $results[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    private function triggerDownloadTracking(string $accessKey, array $photo): void
    {
        $downloadLocation = $photo['links']['download_location'] ?? null;

        if (! is_string($downloadLocation) || $downloadLocation === '') {
            return;
        }

        Http::withHeaders([
            'Authorization' => "Client-ID {$accessKey}",
            'Accept-Version' => 'v1',
        ])->get($downloadLocation);
    }
}
