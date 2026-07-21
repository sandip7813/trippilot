<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Logo
    |--------------------------------------------------------------------------
    |
    | Active logo mark used across the app. Must match a LogoVariant in
    | resources/js/config/brand.ts: plane, compass, pin, globe, monogram
    |
    */

    'logo' => env('TRIPPILOT_LOGO', 'compass'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | TripPilot is India-first. Budget amounts and AI estimates use INR unless
    | the itinerary explicitly includes another ISO 4217 currency code.
    |
    */

    'currency' => env('TRIPPILOT_CURRENCY', 'INR'),

    'currency_locale' => env('TRIPPILOT_CURRENCY_LOCALE', 'en-IN'),

    /*
    |--------------------------------------------------------------------------
    | RAG / Knowledge Base
    |--------------------------------------------------------------------------
    */

    'rag' => [
        'top_k' => (int) env('TRIPPILOT_RAG_TOP_K', 5),
        'minimum_score' => (float) env('TRIPPILOT_RAG_MINIMUM_SCORE', 0.2),
        'chunk_max_characters' => (int) env('TRIPPILOT_RAG_CHUNK_MAX_CHARACTERS', 1800),
        'chunk_overlap_characters' => (int) env('TRIPPILOT_RAG_CHUNK_OVERLAP_CHARACTERS', 200),
    ],

];
