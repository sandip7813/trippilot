<?php

namespace App\Services\Weather\OpenMeteo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenMeteoClient
{
    /**
     * @param  list<string>  $daily
     */
    public function forecast(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        array $daily = ['temperature_2m_max', 'temperature_2m_min', 'precipitation_sum', 'weathercode'],
    ): Response {
        return $this->client(config('integrations.weather.drivers.open_meteo.forecast_url'))
            ->get('/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'daily' => implode(',', $daily),
                'timezone' => 'auto',
            ]);
    }

    /**
     * @param  list<string>  $daily
     */
    public function archive(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        array $daily = ['temperature_2m_max', 'temperature_2m_min', 'precipitation_sum', 'weathercode'],
    ): Response {
        return $this->client(config('integrations.weather.drivers.open_meteo.archive_url'))
            ->get('/archive', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'daily' => implode(',', $daily),
                'timezone' => 'auto',
            ]);
    }

    private function client(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], 2, throw: false);
    }
}
