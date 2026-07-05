<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/dashboard');

    Route::inertia('dashboard', 'admin/Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin/super')->name('admin.super.')->group(function () {
    Route::inertia('settings', 'admin/super/Settings')->name('settings');
});
