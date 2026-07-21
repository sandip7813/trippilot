<?php

namespace App\Providers;

use App\Contracts\Ai\ChatAssistant;
use App\Contracts\Ai\EmbeddingService;
use App\Contracts\Ai\TripGenerator;
use App\Contracts\Maps\PlacesService;
use App\Contracts\Maps\RoutingService;
use App\Contracts\TripCovers\TripCoverGenerator;
use App\Services\Ai\Gemini\GeminiChatAssistant;
use App\Services\Ai\Gemini\GeminiClient;
use App\Services\Ai\Gemini\GeminiEmbeddingService;
use App\Services\Ai\Gemini\GeminiTripCoverGenerator;
use App\Services\Ai\Gemini\GeminiTripGenerator;
use App\Services\Maps\Geoapify\GeoapifyAutocomplete;
use App\Services\Maps\Geoapify\GeoapifyClient;
use App\Services\Maps\Geoapify\GeoapifyPlacesService;
use App\Services\Maps\Geoapify\GeoapifyRoutingService;
use App\Services\Trains\NearestRailheadResolver;
use App\Services\Trains\RailRadarClient;
use App\Services\Trains\RailwayStationResolver;
use App\Services\Trains\TripTrainHaltsService;
use App\Services\Trains\TripTrainService;
use App\Services\TripCovers\PollinationsTripCoverGenerator;
use App\Services\TripCovers\UnsplashTripCoverGenerator;
use App\Services\Trips\TripRouteResolver;
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
        $this->app->singleton(RailRadarClient::class);
        $this->app->singleton(RailwayStationResolver::class);
        $this->app->singleton(NearestRailheadResolver::class);
        $this->app->singleton(TripTrainService::class);
        $this->app->singleton(TripTrainHaltsService::class);
        $this->app->singleton(TripRouteResolver::class);
        $this->app->singleton(OpenWeatherMapClient::class);

        $this->registerMapsServices();
        $this->registerWeatherServices();
        $this->registerAiServices();
        $this->registerTripCoverServices();
    }

    private function registerMapsServices(): void
    {
        $driver = config('integrations.maps.driver');

        $implementations = [
            'geoapify' => [
                RoutingService::class => GeoapifyRoutingService::class,
                PlacesService::class => GeoapifyPlacesService::class,
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
                ChatAssistant::class => GeminiChatAssistant::class,
                EmbeddingService::class => GeminiEmbeddingService::class,
            ],
        ];

        $this->bindContracts($implementations[$driver] ?? []);
    }

    private function registerTripCoverServices(): void
    {
        $this->app->bind(TripCoverGenerator::class, function ($app): TripCoverGenerator {
            $driver = config('integrations.trip_covers.driver', 'unsplash');

            $implementations = [
                'unsplash' => UnsplashTripCoverGenerator::class,
                'pollinations' => PollinationsTripCoverGenerator::class,
                'gemini' => GeminiTripCoverGenerator::class,
            ];

            $implementation = $implementations[$driver] ?? UnsplashTripCoverGenerator::class;

            return $app->make($implementation);
        });
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
