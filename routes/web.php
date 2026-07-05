<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::inertia('trips', 'Trips/Index')->name('trips.index');
    Route::inertia('road-trips', 'RoadTrips/Index')->name('road-trips.index');
});

require __DIR__.'/settings.php';
