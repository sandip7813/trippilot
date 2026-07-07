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
                'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
                'image_enabled' => filter_var(env('GEMINI_IMAGE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trip Cover Images
    |--------------------------------------------------------------------------
    |
    | Supported drivers: unsplash, pollinations, gemini, none
    |
    | Unsplash (recommended) returns real destination photography.
    | Gemini can refine Unsplash search queries to famous landmarks (TRIP_COVER_GEMINI_PROMPT).
    | Pollinations AI art is experimental and often inaccurate for specific places.
    |
    */

    'trip_covers' => [
        'driver' => env('TRIP_COVER_DRIVER', 'unsplash'),
        'enabled' => filter_var(env('TRIP_COVER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'use_gemini_prompt' => filter_var(env('TRIP_COVER_GEMINI_PROMPT', true), FILTER_VALIDATE_BOOLEAN),

        'sizes' => [
            'banner' => [
                'width' => (int) env('TRIP_COVER_BANNER_WIDTH', 1920),
                'height' => (int) env('TRIP_COVER_BANNER_HEIGHT', 900),
            ],
            'thumb' => [
                'width' => (int) env('TRIP_COVER_THUMB_WIDTH', 384),
                'height' => (int) env('TRIP_COVER_THUMB_HEIGHT', 512),
            ],
        ],

        'drivers' => [
            'unsplash' => [
                'access_key' => env('UNSPLASH_ACCESS_KEY'),
            ],
            'pollinations' => [
                'base_url' => env('POLLINATIONS_IMAGE_URL', 'https://image.pollinations.ai/prompt'),
                'model' => env('POLLINATIONS_IMAGE_MODEL', 'flux'),
                'enhance' => filter_var(env('POLLINATIONS_ENHANCE', false), FILTER_VALIDATE_BOOLEAN),
            ],
        ],
    ],

];
