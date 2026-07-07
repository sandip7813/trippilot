<?php

use App\Models\Trip;
use App\Services\Weather\OpenMeteo\OpenMeteoClient;
use App\Services\Weather\OpenMeteo\WeatherCode;
use App\Services\Weather\TripWeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();

    config(['integrations.weather.driver' => 'open_meteo']);
});

test('weather code helper maps common conditions', function () {
    expect(WeatherCode::describe(0)['label'])->toBe('Clear sky')
        ->and(WeatherCode::describe(63)['kind'])->toBe('rain')
        ->and(WeatherCode::describe(95)['kind'])->toBe('storm');
});

test('trip weather uses forecast mode when the trip starts within sixteen days', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07'));

    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'daily' => [
                'time' => ['2026-07-20', '2026-07-21'],
                'temperature_2m_max' => [32.4, 31.1],
                'temperature_2m_min' => [26.2, 25.4],
                'precipitation_sum' => [2.1, 0.0],
                'weathercode' => [2, 0],
            ],
        ]),
    ]);

    $trip = new Trip;
    $trip->forceFill([
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
            'place_id' => 'goa',
            'country_code' => 'in',
        ],
        'start_date' => Carbon::parse('2026-07-20'),
        'end_date' => Carbon::parse('2026-07-21'),
    ]);

    $weather = app(TripWeatherService::class)->forTrip($trip);

    expect($weather)->not->toBeNull()
        ->and($weather['available'])->toBeTrue()
        ->and($weather['mode'])->toBe('forecast')
        ->and($weather['days'])->toHaveCount(2)
        ->and($weather['days'][0]['temperature_max'])->toBe(32)
        ->and($weather['days'][0]['weather_label'])->toBe('Partly cloudy');
});

test('trip weather uses typical mode when the trip starts more than sixteen days away', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-07'));

    Http::fake([
        'archive-api.open-meteo.com/*' => Http::response([
            'daily' => [
                'time' => ['2016-10-03', '2016-10-04'],
                'temperature_2m_max' => [30.0, 29.0],
                'temperature_2m_min' => [24.0, 23.0],
                'precipitation_sum' => [4.0, 0.5],
                'weathercode' => [61, 2],
            ],
        ]),
    ]);

    $trip = new Trip;
    $trip->forceFill([
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
            'place_id' => 'goa',
            'country_code' => 'in',
        ],
        'start_date' => Carbon::parse('2026-10-03'),
        'end_date' => Carbon::parse('2026-10-04'),
    ]);

    $weather = app(TripWeatherService::class)->forTrip($trip);

    expect($weather)->not->toBeNull()
        ->and($weather['available'])->toBeTrue()
        ->and($weather['mode'])->toBe('typical')
        ->and($weather['temperature_min'])->toBeGreaterThan(0)
        ->and($weather['temperature_max'])->toBeGreaterThan(0)
        ->and($weather['sample_years'])->toBe(10);
});

test('trip weather explains when destination coordinates are missing', function () {
    $trip = new Trip;
    $trip->forceFill([
        'destination' => [
            'label' => 'Goa, India',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
            'country_code' => 'in',
        ],
        'start_date' => Carbon::parse('2026-10-03'),
        'end_date' => Carbon::parse('2026-10-10'),
    ]);

    $weather = app(TripWeatherService::class)->forTrip($trip);

    expect($weather)->toMatchArray([
        'available' => false,
        'reason' => 'missing_coordinates',
    ]);
});

test('trip weather explains when weather driver is not open meteo', function () {
    config(['integrations.weather.driver' => 'openweathermap']);

    $trip = new Trip;
    $trip->forceFill([
        'destination' => [
            'label' => 'Goa, India',
            'lat' => 15.2993,
            'lng' => 74.1240,
            'place_id' => 'goa',
            'country_code' => 'in',
        ],
        'start_date' => Carbon::parse('2026-07-20'),
        'end_date' => Carbon::parse('2026-07-21'),
    ]);

    $weather = app(TripWeatherService::class)->forTrip($trip);

    expect($weather)->toMatchArray([
        'available' => false,
        'reason' => 'driver_disabled',
    ]);
});

test('open meteo client calls forecast endpoint without an api key', function () {
    Http::fake([
        'api.open-meteo.com/*' => Http::response(['daily' => ['time' => []]]),
    ]);

    app(OpenMeteoClient::class)->forecast(15.3, 74.1, '2026-07-20', '2026-07-21');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.open-meteo.com/v1/forecast')
            && ! str_contains($request->url(), 'apiKey=')
            && ! str_contains($request->url(), 'appid=');
    });
});
