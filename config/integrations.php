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
    | Supported drivers: rotating, unsplash, pollinations, gemini, none
    |
    | rotating (default) tries Wikipedia, Wikimedia Commons, Unsplash, then Pollinations.
    | Unsplash remains available as a later step in the rotation ladder.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Indian Railways (Train Timings)
    |--------------------------------------------------------------------------
    |
    | Supported drivers: railradar
    |
    */

    'trains' => [
        'driver' => env('TRAIN_DRIVER', 'railradar'),
        'cache_ttl' => (int) env('TRAIN_CACHE_TTL', 43200),
        'station_lookup_cache_ttl' => (int) env('TRAIN_STATION_LOOKUP_CACHE_TTL', 604800),
        'max_results' => (int) env('TRAIN_MAX_RESULTS', 12),

        'drivers' => [
            'railradar' => [
                'api_key' => env('RAILRADAR_API_KEY'),
                'base_url' => 'https://api.railradar.in/v1',
            ],
        ],

        /*
         * Map common place names (normalized) to IR station codes when Geoapify
         * and label matching cannot resolve a station reliably.
         */
        'station_aliases' => [
            'shantiniketan' => 'BHP',
            'bolpur' => 'BHP',
            'kolkata' => 'HWH',
            'calcutta' => 'HWH',
            'howrah' => 'HWH',
            'delhi' => 'NDLS',
            'new delhi' => 'NDLS',
            'mumbai' => 'BCT',
            'bombay' => 'BCT',
            'bengaluru' => 'SBC',
            'bangalore' => 'SBC',
            'chennai' => 'MAS',
            'madras' => 'MAS',
            'hyderabad' => 'HYB',
            'pune' => 'PUNE',
            'goa' => 'MAO',
            'margao' => 'MAO',
            'jaipur' => 'JP',
            'agra' => 'AGC',
            'varanasi' => 'BSB',
            'lucknow' => 'LKO',
            'ahmedabad' => 'ADI',
            'chandigarh' => 'CDG',
        ],

        /*
         * Hill stations and off-network places mapped to their nearest
         * mainline railhead for fallback train searches.
         */
        'nearest_railheads' => [
            'shimla' => [
                'code' => 'KLK',
                'name' => 'Kalka',
                'last_mile' => 'Kalka to Shimla: toy train, bus, or taxi (~90 km).',
            ],
            'manali' => [
                'code' => 'CDG',
                'name' => 'Chandigarh',
                'last_mile' => 'Chandigarh to Manali: bus or taxi (~310 km through the hills).',
            ],
            'mussoorie' => [
                'code' => 'DDN',
                'name' => 'Dehradun',
                'last_mile' => 'Dehradun to Mussoorie: bus or taxi (~35 km).',
            ],
            'nainital' => [
                'code' => 'KGM',
                'name' => 'Kathgodam',
                'last_mile' => 'Kathgodam to Nainital: bus or taxi (~35 km).',
            ],
            'darjeeling' => [
                'code' => 'NJP',
                'name' => 'New Jalpaiguri',
                'last_mile' => 'NJP to Darjeeling: toy train, bus, or taxi (~70 km).',
            ],
            'gangtok' => [
                'code' => 'NJP',
                'name' => 'New Jalpaiguri',
                'last_mile' => 'NJP to Gangtok: shared taxi or bus (~120 km).',
            ],
            'dharamshala' => [
                'code' => 'PTA',
                'name' => 'Pathankot',
                'last_mile' => 'Pathankot to Dharamshala: bus or taxi (~90 km).',
            ],
            'mcleod ganj' => [
                'code' => 'PTA',
                'name' => 'Pathankot',
                'last_mile' => 'Pathankot to McLeod Ganj: bus or taxi (~90 km).',
            ],
            'ooty' => [
                'code' => 'MTM',
                'name' => 'Mettupalayam',
                'last_mile' => 'Mettupalayam to Ooty: Nilgiri toy train or bus (~50 km).',
            ],
            'munnar' => [
                'code' => 'ERS',
                'name' => 'Ernakulam Junction',
                'last_mile' => 'Ernakulam to Munnar: bus or taxi (~130 km).',
            ],
        ],
    ],

    'trip_covers' => [
        'driver' => env('TRIP_COVER_DRIVER', 'rotating'),
        'enabled' => filter_var(env('TRIP_COVER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'use_gemini_prompt' => filter_var(env('TRIP_COVER_GEMINI_PROMPT', true), FILTER_VALIDATE_BOOLEAN),
        'pollinations_fallback' => filter_var(env('TRIP_COVER_POLLINATIONS_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),

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
            'wikimedia' => [
                'user_agent' => env('TRIP_COVER_WIKIMEDIA_USER_AGENT', 'TripPilot/1.0 (trip-cover@trippilot.test)'),
                'commons_geo_radius_meters' => (int) env('TRIP_COVER_COMMONS_GEO_RADIUS_METERS', 10000),
            ],
        ],
    ],

];
