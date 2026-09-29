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

    /*
    |--------------------------------------------------------------------------
    | Trip Reminders
    |--------------------------------------------------------------------------
    |
    | Days before a trip's start date on which owners and accepted
    | collaborators are reminded (email + in-app).
    |
    */

    'reminder_days' => [7, 1],

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

    /*
    |--------------------------------------------------------------------------
    | Open Trips
    |--------------------------------------------------------------------------
    |
    | Any user can publish a trip publicly as an "open trip" for others to
    | discover, contact and request to join. This caps how many a single
    | user may keep active (published and not yet past) at once.
    |
    */

    'open_trips' => [
        'max_active_per_user' => (int) env('TRIPPILOT_OPEN_TRIPS_MAX_ACTIVE_PER_USER', 3),
    ],

];
