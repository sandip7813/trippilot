<?php

use App\Services\Maps\Geoapify\GeoapifyAutocomplete;
use App\Services\Maps\Geoapify\GeoapifyClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('geoapify autocomplete maps json results', function () {
    config([
        'integrations.maps.drivers.geoapify.api_key' => 'test-key',
        'integrations.maps.drivers.geoapify.base_url' => 'https://api.geoapify.com/v1',
        'integrations.maps.default_country' => 'in',
    ]);

    Http::fake([
        'api.geoapify.com/*' => Http::response([
            'results' => [
                [
                    'formatted' => 'Goa, India',
                    'lat' => 15.2993,
                    'lon' => 74.1240,
                    'country_code' => 'in',
                    'place_id' => 'place-goa',
                ],
            ],
        ]),
    ]);

    $autocomplete = new GeoapifyAutocomplete(app(GeoapifyClient::class));

    expect($autocomplete->search('Goa'))->toBe([
        [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
            'place_id' => 'place-goa',
            'country_code' => 'in',
        ],
    ]);
});

test('geoapify autocomplete returns empty results when api key is missing', function () {
    config(['integrations.maps.drivers.geoapify.api_key' => null]);

    $autocomplete = new GeoapifyAutocomplete(app(GeoapifyClient::class));

    expect($autocomplete->search('Goa'))->toBe([]);
});
