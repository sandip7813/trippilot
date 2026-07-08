<?php

namespace App\Services\Weather\OpenWeatherMap;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenWeatherMapClient
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->client()
            ->get($endpoint, [
                ...$query,
                'appid' => $this->apiKey(),
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
        return config('integrations.weather.drivers.openweathermap.base_url');
    }

    private function apiKey(): string
    {
        return (string) config('integrations.weather.drivers.openweathermap.api_key');
    }
}
