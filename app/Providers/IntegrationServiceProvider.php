<?php

namespace App\Providers;

use App\Contracts\Ai\TripGenerator;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiTripGenerator;
use App\Services\Maps\Geoapify\GeoapifyAutocomplete;
use App\Services\Maps\Geoapify\GeoapifyClient;
use App\Services\Weather\OpenMeteo\OpenMeteoClient;
use App\Services\Weather\OpenWeatherMap\OpenWeatherMapClient;
use App\Services\Weather\TripWeatherService;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register integration clients and service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(GeoapifyClient::class);
        $this->app->singleton(GeoapifyAutocomplete::class);
        $this->app->singleton(GeminiClient::class);
        $this->app->singleton(OpenMeteoClient::class);
        $this->app->singleton(TripWeatherService::class);
        $this->app->singleton(OpenWeatherMapClient::class);

        $this->registerMapsServices();
        $this->registerWeatherServices();
        $this->registerAiServices();
    }

    private function registerMapsServices(): void
    {
        $driver = config('integrations.maps.driver');

        $implementations = [
            'geoapify' => [
                // GeocodingService::class => GeoapifyGeocodingService::class,
                // RoutingService::class => GeoapifyRoutingService::class,
                // PlacesService::class => GeoapifyPlacesService::class,
            ],
            'google' => [
                // GeocodingService::class => GoogleGeocodingService::class,
            ],
        ];

        $this->bindContracts($implementations[$driver] ?? []);
    }

    private function registerWeatherServices(): void
    {
        $driver = config('integrations.weather.driver');

        $implementations = [
            'open_meteo' => [
                // WeatherService::class => OpenMeteoTripWeatherService::class,
            ],
            'openweathermap' => [
                // WeatherService::class => OpenWeatherMapService::class,
            ],
        ];

        $this->bindContracts($implementations[$driver] ?? []);
    }

    private function registerAiServices(): void
    {
        $driver = config('integrations.ai.driver');

        $implementations = [
            'gemini' => [
                TripGenerator::class => GeminiTripGenerator::class,
                // ChatAssistant::class => GeminiChatAssistant::class,
                // EmbeddingService::class => GeminiEmbeddingService::class,
            ],
        ];

        $this->bindContracts($implementations[$driver] ?? []);
    }

    /**
     * @param  array<class-string, class-string>  $bindings
     */
    private function bindContracts(array $bindings): void
    {
        foreach ($bindings as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }
}
