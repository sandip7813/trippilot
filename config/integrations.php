<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maps Integration
    |--------------------------------------------------------------------------
    |
    | Supported drivers: geoapify, google (future)
    |
    */

    'maps' => [
        'driver' => env('MAPS_DRIVER', 'geoapify'),
        'default_country' => env('MAPS_DEFAULT_COUNTRY', 'in'),

        'drivers' => [
            'geoapify' => [
                'api_key' => env('GEOAPIFY_API_KEY'),
                'base_url' => 'https://api.geoapify.com/v1',
            ],
            'google' => [
                'api_key' => env('GOOGLE_MAPS_API_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Weather Integration
    |--------------------------------------------------------------------------
    */

    'weather' => [
        'driver' => env('WEATHER_DRIVER', 'open_meteo'),

        'drivers' => [
            'open_meteo' => [
                'forecast_url' => 'https://api.open-meteo.com/v1',
                'archive_url' => 'https://archive-api.open-meteo.com/v1',
            ],
            'openweathermap' => [
                'api_key' => env('OPENWEATHERMAP_API_KEY'),
                'base_url' => 'https://api.openweathermap.org/data/2.5',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Integration
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'driver' => env('AI_DRIVER', 'gemini'),

        'drivers' => [
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
                'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
            ],
        ],
    ],

];
