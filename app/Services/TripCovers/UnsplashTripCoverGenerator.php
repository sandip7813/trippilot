<?php

namespace App\Services\TripCovers;

use App\Contracts\TripCovers\TripCoverGenerator;
use App\Data\TripCovers\TripCoverCandidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashTripCoverGenerator implements TripCoverGenerator
{
    public function __construct(
        private TripCoverPlacePhrase $placePhrase,
        private GeminiTripCoverSearchQueryEnhancer $searchQueryEnhancer,
        private TripCoverPhotoValidator $photoValidator,
    ) {}

    public function generate(array $destination, ?string $travelStyle, int $width, int $height): ?string
    {
        $candidate = $this->candidates($destination, $travelStyle)[0] ?? null;

        if ($candidate === null) {
            return null;
        }

        return $this->downloadCandidate($candidate, $destination);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    public function candidates(array $destination, ?string $travelStyle): array
    {
        $accessKey = (string) config('integrations.trip_covers.drivers.unsplash.access_key');

        if ($accessKey === '') {
            Log::warning('Unsplash trip cover generation skipped because UNSPLASH_ACCESS_KEY is not configured.');

            return [];
        }

        $places = $this->placePhrase->searchPhrases($destination);

        if ($places === []) {
            return [];
        }

        $candidates = [];
        $seenIds = [];

        foreach ($this->placePhrase->curatedQueries($destination) as $query) {
            foreach ($this->matchingPhotos($accessKey, $query, $this->placePhrase->resolve($destination), $destination) as $photo) {
                $candidate = $this->candidateFromPhoto($photo);

                if ($candidate === null || isset($seenIds[$candidate->ref])) {
                    continue;
                }

                $seenIds[$candidate->ref] = true;
                $candidates[] = $candidate;
            }
        }

        foreach ($places as $place) {
            foreach ($this->searchQueries($destination, $place, $travelStyle) as $query) {
                foreach ($this->matchingPhotos($accessKey, $query, $place, $destination) as $photo) {
                    $candidate = $this->candidateFromPhoto($photo);

                    if ($candidate === null || isset($seenIds[$candidate->ref])) {
                        continue;
                    }

                    $seenIds[$candidate->ref] = true;
                    $candidates[] = $candidate;
                }
            }
        }

        if ($candidates === []) {
            Log::warning('Unsplash trip cover search returned no photos.', [
                'destination' => $destination['label'] ?? null,
                'places_tried' => $places,
            ]);
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $photo
     */
    private function candidateFromPhoto(array $photo): ?TripCoverCandidate
    {
        $imageUrl = $photo['urls']['regular'] ?? $photo['urls']['full'] ?? null;

        if (! is_string($imageUrl) || $imageUrl === '') {
            return null;
        }

        $photoId = (string) ($photo['id'] ?? '');

        if ($photoId === '') {
            $photoId = md5($imageUrl);
        }

        return new TripCoverCandidate(
            ref: 'unsplash:'.$photoId,
            imageUrl: $imageUrl,
            attribution: [
                'source' => 'Unsplash',
                'author' => (string) data_get($photo, 'user.name', ''),
                'license' => 'Unsplash License',
                'credit_url' => (string) data_get($photo, 'links.html', ''),
                'description' => (string) ($photo['description'] ?? $photo['alt_description'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<array<string, mixed>>
     */
    private function matchingPhotos(string $accessKey, string $query, string $place, array $destination): array
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
                'per_page' => 15,
            ]);

        if ($response->failed()) {
            Log::warning('Unsplash trip cover search failed.', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return [];
        }

        /** @var list<array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        if ($results === []) {
            return [];
        }

        $terms = $this->significantTerms($place, $query);
        $matches = [];

        foreach ($results as $photo) {
            if ($this->photoValidator->matchesDestination($photo, $destination, $terms)) {
                $matches[] = $photo;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function downloadCandidate(TripCoverCandidate $candidate, array $destination): ?string
    {
        $response = Http::timeout(60)->get($candidate->imageUrl);

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
     * @param  array<string, mixed>  $destination
     * @return list<string>
     */
    private function searchQueries(array $destination, string $place, ?string $travelStyle): array
    {
        $queries = $this->searchQueryEnhancer->queries($destination, $place, $travelStyle);

        $fallbacks = [
            "{$place} landmark",
            "{$place} tourism",
            "{$place} travel",
            $place,
        ];

        return array_values(array_unique(array_filter([...$queries, ...$fallbacks])));
    }

    /**
     * @return list<string>
     */
    private function significantTerms(string $place, string $query): array
    {
        $generic = [
            'landmark',
            'tourism',
            'travel',
            'india',
            'photo',
            'city',
            'town',
            'village',
        ];

        $terms = [];

        foreach (preg_split('/[\s,]+/', strtolower("{$place} {$query}")) ?: [] as $term) {
            $term = trim($term);

            if (strlen($term) < 4 || in_array($term, $generic, true)) {
                continue;
            }

            $terms[] = $term;
        }

        return array_values(array_unique($terms));
    }
}
