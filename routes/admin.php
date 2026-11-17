<?php

use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KnowledgeDocumentController;
use App\Http\Controllers\Admin\Super\ContactMessageController;
use App\Http\Controllers\Admin\Super\CreditGrantController;
use App\Http\Controllers\Admin\Super\CreditPackController;
use App\Http\Controllers\Admin\Super\CreditPurchaseController;
use App\Http\Controllers\Admin\Super\CreditSettingsController;
use App\Http\Controllers\Admin\Super\SettingsController;
use App\Http\Controllers\Admin\Super\UserCreditController;
use App\Http\Controllers\Admin\TripController as AdminTripController;
use App\Http\Controllers\Admin\TripReportController;
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

    Route::get('trip-reports', [TripReportController::class, 'index'])->name('trip-reports.index');
    Route::post('trip-reports/{report}/takedown', [TripReportController::class, 'takedown'])->name('trip-reports.takedown');
    Route::post('trip-reports/{report}/dismiss', [TripReportController::class, 'dismiss'])->name('trip-reports.dismiss');

    Route::resource('knowledge', KnowledgeDocumentController::class)
        ->except(['show']);

    Route::post('content-pages/preview', [ContentPageController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('content-pages.preview');
    Route::resource('content-pages', ContentPageController::class)
        ->except(['show'])
        ->parameters(['content-pages' => 'contentPage']);
});

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin/super')->name('admin.super.')->group(function () {
    Route::get('settings', SettingsController::class)->name('settings');
    Route::patch('settings/integrations', [SettingsController::class, 'updateIntegrations'])
        ->name('settings.integrations');

    Route::get('credits', [CreditSettingsController::class, 'show'])->name('credits.show');
    Route::put('credits', [CreditSettingsController::class, 'update'])->name('credits.update');
    Route::put('credit-packs', [CreditPackController::class, 'update'])->name('credit-packs.update');
    Route::get('credit-purchases', [CreditPurchaseController::class, 'index'])->name('credit-purchases.index');
    Route::get('credit-grants', [CreditGrantController::class, 'index'])->name('credit-grants.index');
    Route::post('users/{user}/credits', [UserCreditController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('users.credits.store');

    Route::get('contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::post('contact-messages/{contactMessage}/reply', [ContactMessageController::class, 'reply'])
        ->middleware('throttle:20,1')
        ->name('contact-messages.reply');
    Route::patch('contact-messages/{contactMessage}/status', [ContactMessageController::class, 'updateStatus'])
        ->name('contact-messages.status');
});
