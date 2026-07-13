<?php

namespace App\Services\TripCovers;

use App\Data\TripCovers\TripCoverCandidate;
use Illuminate\Support\Facades\Log;

class CommonsGeoImageClient
{
    public function __construct(
        private WikimediaHttp $wikimediaHttp,
        private WikimediaCoverRelevance $relevance,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @return list<TripCoverCandidate>
     */
    public function candidates(array $destination, int $width): array
    {
        $lat = $destination['lat'] ?? null;
        $lng = $destination['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return [];
        }

        $radius = (int) config('integrations.trip_covers.drivers.wikimedia.commons_geo_radius_meters', 10000);

        $response = $this->wikimediaHttp->client()->get('https://commons.wikimedia.org/w/api.php', [
            'action' => 'query',
            'format' => 'json',
            'generator' => 'geosearch',
            'ggsprimary' => 'all',
            'ggsnamespace' => 6,
            'ggsradius' => min(max($radius, 100), 10000),
            'ggscoord' => ((float) $lat).'|'.((float) $lng),
            'ggslimit' => 15,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata|mime',
            'iiurlwidth' => $width,
        ]);

        if ($response->failed()) {
            Log::warning('Commons geo trip cover search failed.', [
                'status' => $response->status(),
                'destination' => $destination['label'] ?? null,
            ]);

            return [];
        }

        /** @var array<string, mixed> $pages */
        $pages = $response->json('query.pages') ?? [];

        return $this->mapPagesToCandidates($destination, $pages);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @param  array<string, mixed>  $pages
     * @return list<TripCoverCandidate>
     */
    private function mapPagesToCandidates(array $destination, array $pages): array
    {
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

            $mime = strtolower((string) ($imageInfo['mime'] ?? ''));

            if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
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
                ref: 'commons_geo:'.$title,
                imageUrl: $imageUrl,
                attribution: $this->attributionFromMetadata($title, $imageInfo),
            );
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $imageInfo
     * @return array<string, string|null>
     */
    private function attributionFromMetadata(string $title, array $imageInfo): array
    {
        $metadata = $imageInfo['extmetadata'] ?? [];
        $artist = is_array($metadata) ? ($metadata['Artist']['value'] ?? null) : null;
        $license = is_array($metadata) ? ($metadata['LicenseShortName']['value'] ?? null) : null;

        return [
            'source' => 'Wikimedia Commons',
            'author' => is_string($artist) ? strip_tags($artist) : null,
            'license' => is_string($license) ? strip_tags($license) : 'See Commons file page',
            'credit_url' => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)),
            'description' => $title,
        ];
    }
}
