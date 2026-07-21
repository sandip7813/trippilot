<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA
    |--------------------------------------------------------------------------
    |
    | Register keys at https://www.google.com/recaptcha/admin
    | Use reCAPTCHA v3 (score-based, invisible) for the signup form.
    |
    */

    'enabled' => env('RECAPTCHA_ENABLED', true),

    'site_key' => env('RECAPTCHA_SITE_KEY'),

    'secret_key' => env('RECAPTCHA_SECRET_KEY'),

    'action' => env('RECAPTCHA_ACTION', 'register'),

    'score_threshold' => (float) env('RECAPTCHA_SCORE_THRESHOLD', 0.5),

];
