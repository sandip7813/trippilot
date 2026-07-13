<?php

namespace App\Services\TripCovers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WikimediaHttp
{
    public function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config(
                'integrations.trip_covers.drivers.wikimedia.user_agent',
                'TripPilot/1.0 (https://trippilot.test; trip-cover@trippilot.test)',
            ),
        ])->timeout(25);
    }
}
