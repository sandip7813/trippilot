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

    /*
    |--------------------------------------------------------------------------
    | AI Credits
    |--------------------------------------------------------------------------
    |
    | When enabled, regular users spend credits on AI features (admins and
    | super admins are exempt). Super admins can switch this on from the
    | admin panel, which overrides the env value. New users receive the
    | signup bonus once their email address is verified.
    |
    */

    'credits' => [
        'enabled' => (bool) env('TRIPPILOT_CREDITS_ENABLED', false),
        'signup_bonus' => (int) env('TRIPPILOT_CREDITS_SIGNUP_BONUS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal & Business Details
    |--------------------------------------------------------------------------
    |
    | Shown on the Terms, Privacy, Refund & Cancellation and Pricing pages,
    | which payment providers such as Razorpay review before activation.
    | Page text is edited in Admin → Content pages, where {business_name},
    | {support_email} and similar placeholders show these values.
    |
    */

    'legal' => [
        'business_name' => env('TRIPPILOT_LEGAL_NAME', 'TripPilot'),
        'support_email' => env('TRIPPILOT_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
        'support_phone' => env('TRIPPILOT_SUPPORT_PHONE'),
        'address' => env('TRIPPILOT_BUSINESS_ADDRESS'),
        'jurisdiction' => env('TRIPPILOT_LEGAL_JURISDICTION', 'India'),
    ],

];
