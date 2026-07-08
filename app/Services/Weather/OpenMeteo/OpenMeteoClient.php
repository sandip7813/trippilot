<?php

namespace App\Services\Weather\OpenMeteo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
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

    /**
     * @param  list<array{key: string, start_date: string, end_date: string}>  $periods
     * @param  list<string>  $daily
     * @return array<string, Response>
     */
    public function archiveMany(
        float $latitude,
        float $longitude,
        array $periods,
        array $daily = ['temperature_2m_max', 'temperature_2m_min', 'precipitation_sum', 'weathercode'],
    ): array {
        $baseUrl = config('integrations.weather.drivers.open_meteo.archive_url');

        $responses = Http::pool(function (Pool $pool) use ($baseUrl, $latitude, $longitude, $periods, $daily): array {
            $requests = [];

            foreach ($periods as $period) {
                $requests[] = $pool->as($period['key'])
                    ->baseUrl($baseUrl)
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->retry([100, 500], 2, throw: false)
                    ->get('/archive', [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'start_date' => $period['start_date'],
                        'end_date' => $period['end_date'],
                        'daily' => implode(',', $daily),
                        'timezone' => 'auto',
                    ]);
            }

            return $requests;
        });

        $result = [];

        foreach ($responses as $key => $response) {
            if ($response instanceof Response) {
                $result[$key] = $response;
            }
        }

        return $result;
    }

    private function client(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], 2, throw: false);
    }
}
