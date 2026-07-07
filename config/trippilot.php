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

];
