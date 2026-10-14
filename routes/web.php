<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Master\ActivityIndex;
use App\Livewire\Master\CostItemIndex;
use App\Livewire\Master\DestinationIndex;
use App\Livewire\Master\HotelIndex;
use App\Livewire\Master\MealIndex;
use App\Livewire\Master\VehicleIndex;
use App\Livewire\Master\VendorIndex;

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

Route::middleware(['auth', 'permission:master.view'])
    ->prefix('master')->name('master.')->group(function () {
        Route::redirect('/', '/master/vendors');
        Route::get('vendors', VendorIndex::class)->name('vendors');
        Route::get('hotels', HotelIndex::class)->name('hotels');
        Route::get('vehicles', VehicleIndex::class)->name('vehicles');
        Route::get('destinations', DestinationIndex::class)->name('destinations');
        Route::get('activities', ActivityIndex::class)->name('activities');
        Route::get('meals', MealIndex::class)->name('meals');
        Route::get('cost-items', CostItemIndex::class)->name('cost-items');
    });

require __DIR__.'/auth.php';
