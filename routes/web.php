<?php

use App\Http\Controllers\Auth\SendRegistrationOtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationSearchController;
use App\Http\Controllers\RoadTripController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('register/otp', SendRegistrationOtpController::class)
        ->middleware('throttle:registration-otp')
        ->name('register.otp');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('locations/search', LocationSearchController::class)
        ->middleware('throttle:30,1')
        ->name('locations.search');

    Route::patch('trips/{trip}/favorite', [TripController::class, 'toggleFavorite'])
        ->name('trips.favorite');
    Route::post('trips/{trip}/generate', [TripController::class, 'generateItinerary'])
        ->middleware('throttle:5,1')
        ->name('trips.generate');
    Route::post('trips/{trip}/chat', [TripController::class, 'chat'])
        ->middleware('throttle:10,1')
        ->name('trips.chat');
    Route::post('trips/{trip}/cover', [TripController::class, 'syncCover'])
        ->middleware('throttle:5,1')
        ->name('trips.cover');
    Route::post('trips/{trip}/cover/upload', [TripController::class, 'uploadCover'])
        ->middleware('throttle:10,1')
        ->name('trips.cover.upload');
    Route::get('trips/{trip}/trains/{trainNumber}/halts', [TripController::class, 'trainHalts'])
        ->middleware('throttle:30,1')
        ->name('trips.trains.halts');
    Route::resource('trips', TripController::class);

    Route::post('road-trips/{trip}/route', [RoadTripController::class, 'computeRoute'])
        ->name('road-trips.route');
    Route::post('road-trips/{trip}/suggest-breaks', [RoadTripController::class, 'suggestBreaks'])
        ->middleware('throttle:5,1')
        ->name('road-trips.suggest-breaks');
    Route::post('road-trips/{trip}/amenities', [RoadTripController::class, 'amenities'])
        ->name('road-trips.amenities');
    Route::post('road-trips/{trip}/accept-break', [RoadTripController::class, 'acceptBreak'])
        ->name('road-trips.accept-break');
    Route::delete('road-trips/{trip}/stops', [RoadTripController::class, 'removeStop'])
        ->name('road-trips.remove-stop');
    Route::post('road-trips/{trip}/cover', [RoadTripController::class, 'syncCover'])
        ->middleware('throttle:5,1')
        ->name('road-trips.cover');
    Route::post('road-trips/{trip}/cover/upload', [RoadTripController::class, 'uploadCover'])
        ->middleware('throttle:10,1')
        ->name('road-trips.cover.upload');
    Route::resource('road-trips', RoadTripController::class)->except(['destroy']);
});

require __DIR__.'/settings.php';
