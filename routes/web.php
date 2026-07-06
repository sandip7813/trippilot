<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::patch('trips/{trip}/favorite', [TripController::class, 'toggleFavorite'])
        ->name('trips.favorite');
    Route::post('trips/{trip}/generate', [TripController::class, 'generateItinerary'])
        ->middleware('throttle:5,1')
        ->name('trips.generate');
    Route::resource('trips', TripController::class);

    Route::inertia('road-trips', 'RoadTrips/Index')->name('road-trips.index');
});

require __DIR__.'/settings.php';
