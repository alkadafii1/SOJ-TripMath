<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware('auth')->group(function () {
    Route::get('/master', fn () => 'Halaman master data')
        ->middleware('permission:master.view');

    Route::get('/keuangan', fn () => 'Halaman keuangan')
        ->middleware('can:view-finance');
});

require __DIR__.'/auth.php';
