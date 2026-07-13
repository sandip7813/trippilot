<?php

namespace App\Services\TripCovers;

use App\Data\TripCovers\TripCoverCandidate;
use Illuminate\Support\Facades\Log;

class CommonsTextImageClient
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
    public function candidates(array $destination, int $width): array
    {
        $queries = array_values(array_unique([
            ...$this->placePhrase->curatedQueries($destination),
            ...$this->placePhrase->searchPhrases($destination),
        ]));

        $candidates = [];

        foreach ($queries as $query) {
            foreach ($this->searchFiles($destination, $query, $width) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    private function searchFiles(array $destination, string $query, int $width): array
    {
        $response = $this->wikimediaHttp->client()->get('https://commons.wikimedia.org/w/api.php', [
            'action' => 'query',
            'format' => 'json',
            'generator' => 'search',
            'gsrsearch' => 'filetype:bitmap|drawing '.$query,
            'gsrnamespace' => 6,
            'gsrlimit' => 8,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata|mime',
            'iiurlwidth' => $width,
        ]);

        if ($response->failed()) {
            Log::warning('Commons text trip cover search failed.', [
                'query' => $query,
                'status' => $response->status(),
            ]);

            return [];
        }

        /** @var array<string, mixed> $pages */
        $pages = $response->json('query.pages') ?? [];

        $candidates = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $title = (string) ($page['title'] ?? '');

            if ($title === '' || ! str_starts_with($title, 'File:')) {
                continue;
            }

            $imageInfo = $page['imageinfo'][0] ?? null;

            if (! is_array($imageInfo)) {
                continue;
            }

            $imageUrl = (string) ($imageInfo['thumburl'] ?? $imageInfo['url'] ?? '');

            if ($imageUrl === '') {
                continue;
            }

            if (! $this->relevance->matchesDestination($destination, $title)) {
                continue;
            }

            $candidates[] = new TripCoverCandidate(
                ref: 'commons_text:'.$title,
                imageUrl: $imageUrl,
                attribution: [
                    'source' => 'Wikimedia Commons',
                    'author' => null,
                    'license' => 'See Commons file page',
                    'credit_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)),
                    'description' => $title,
                ],
            );
        }

        return $candidates;
    }
}
