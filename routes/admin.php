<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KnowledgeDocumentController;
use App\Http\Controllers\Admin\Super\SettingsController;
use App\Http\Controllers\Admin\TripController as AdminTripController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/dashboard');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('trips', [AdminTripController::class, 'index'])->name('trips.index');
    Route::patch('trips/{trip}/status', [AdminTripController::class, 'updateStatus'])->name('trips.status');
    Route::delete('trips/{trip}', [AdminTripController::class, 'destroy'])->name('trips.destroy');

    Route::resource('knowledge', KnowledgeDocumentController::class)
        ->except(['show']);
});

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin/super')->name('admin.super.')->group(function () {
    Route::get('settings', SettingsController::class)->name('settings');
    Route::patch('settings/integrations', [SettingsController::class, 'updateIntegrations'])
        ->name('settings.integrations');
});
