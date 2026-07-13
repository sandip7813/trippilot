<?php

namespace App\Services\Trains;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RailRadarClient
{
    public function stationsLookup(): Response
    {
        return $this->client()->get('/lookup/stations');
    }

    public function trainsBetween(
        string $from,
        string $to,
        ?string $date = null,
        bool $live = false,
    ): Response {
        $query = [
            'live' => $live ? 'true' : 'false',
        ];

        if ($date !== null) {
            $query['date'] = $date;
        }

        return $this->client()->get("/trains/between/{$from}/{$to}", $query);
    }

    public function trainSchedule(string $number, ?string $date = null): Response
    {
        $query = [];

        if ($date !== null) {
            $query['date'] = $date;
        }

        return $this->client()->get("/trains/{$number}", $query);
    }

    public function trainLive(string $number, ?string $date = null, bool $haltsOnly = true): Response
    {
        $query = [
            'haltsOnly' => $haltsOnly ? 'true' : 'false',
        ];

        if ($date !== null) {
            $query['date'] = $date;
        }

        return $this->client()->get("/trains/{$number}/live", $query);
    }

    private function client(): PendingRequest
    {
        $config = config('integrations.trains.drivers.railradar');

        return Http::baseUrl($config['base_url'])
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500], 2, throw: false);
    }
}
