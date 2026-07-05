<?php

namespace App\Services\Maps\Geoapify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeoapifyClient
{
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->client()
            ->get($endpoint, [
                ...$query,
                'apiKey' => $this->apiKey(),
            ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(10)
            ->connectTimeout(5)
            ->retry([100, 500], 2, throw: false);
    }

    private function baseUrl(): string
    {
        return config('integrations.maps.drivers.geoapify.base_url');
    }

    private function apiKey(): string
    {
        return (string) config('integrations.maps.drivers.geoapify.api_key');
    }
}
