<?php

namespace App\Services\TripCovers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripCoverImageDownloader
{
    public function __construct(private WikimediaHttp $wikimediaHttp) {}

    public function download(string $imageUrl): ?string
    {
        if ($imageUrl === '') {
            return null;
        }

        if (str_starts_with($imageUrl, '//')) {
            $imageUrl = 'https:'.$imageUrl;
        }

        $client = str_contains($imageUrl, 'wikimedia.org') || str_contains($imageUrl, 'wikipedia.org')
            ? $this->wikimediaHttp->client()
            : Http::timeout(60);

        $response = $client->get($imageUrl);

        if ($response->failed()) {
            Log::warning('Trip cover image download failed.', [
                'status' => $response->status(),
                'url' => $imageUrl,
            ]);

            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $bytes = $response->body();

        return $bytes !== '' ? $bytes : null;
    }
}
