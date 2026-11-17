<?php

use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Incoming webhooks. These routes get no session or CSRF middleware, so every
| controller here must verify the sender's signature itself.
*/

Route::post('webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.razorpay');
