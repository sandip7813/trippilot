<?php

namespace App\Services\TripCovers;

use App\Data\TripCovers\TripCoverCandidate;
use Illuminate\Support\Facades\Log;

class WikipediaPlaceImageClient
{
    public function __construct(
        private WikimediaHttp $wikimediaHttp,
        private TripCoverPlacePhrase $placePhrase,
        private WikimediaCoverRelevance $relevance,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    public function candidates(array $destination): array
    {
        $candidates = [];

        foreach ($this->titleCandidates($destination) as $title) {
            $candidate = $this->candidateForTitle($destination, $title);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<string>
     */
    private function titleCandidates(array $destination): array
    {
        $phrases = $this->placePhrase->searchPhrases($destination);
        $titles = [];

        foreach ($phrases as $phrase) {
            $primary = trim(explode(',', $phrase)[0] ?? $phrase);

            if ($primary !== '') {
                $titles[] = $primary;
            }
        }

        foreach ($this->placePhrase->wikipediaTitles($destination) as $title) {
            $titles[] = $title;
        }

        return array_values(array_unique($titles));
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function candidateForTitle(array $destination, string $title): ?TripCoverCandidate
    {
        $wikiTitle = str_replace(' ', '_', trim($title));
        $response = $this->wikimediaHttp->client()->get(
            'https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($wikiTitle),
        );

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            Log::warning('Wikipedia trip cover summary request failed.', [
                'title' => $title,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        $imageUrl = $data['originalimage']['source'] ?? $data['thumbnail']['source'] ?? null;

        if (! is_string($imageUrl) || $imageUrl === '') {
            return null;
        }

        $description = is_string($data['description'] ?? null) ? $data['description'] : $title;
        $pageUrl = is_string($data['content_urls']['desktop']['page'] ?? null)
            ? $data['content_urls']['desktop']['page']
            : 'https://en.wikipedia.org/wiki/'.$wikiTitle;

        if (! $this->relevance->matchesDestination($destination, $title, $description, (string) ($data['title'] ?? $title))) {
            return null;
        }

        return new TripCoverCandidate(
            ref: 'wikipedia:'.$wikiTitle,
            imageUrl: $imageUrl,
            attribution: [
                'source' => 'Wikipedia',
                'author' => null,
                'license' => 'See Wikipedia media license',
                'credit_url' => $pageUrl,
                'description' => $description,
            ],
        );
    }
}
