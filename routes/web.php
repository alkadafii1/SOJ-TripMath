<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Master\ActivityIndex;
use App\Livewire\Master\CostItemIndex;
use App\Livewire\Master\DestinationIndex;
use App\Livewire\Master\HotelIndex;
use App\Livewire\Master\MealIndex;
use App\Livewire\Master\VehicleIndex;
use App\Livewire\Master\VendorIndex;
use App\Livewire\Package\PackageDetail;
use App\Livewire\Package\PackageIndex;
use App\Livewire\Trip\TripForm;
use App\Livewire\Trip\TripIndex;
use App\Livewire\Trip\TripShow;
use App\Livewire\Pricing\PricingRuleIndex;
use App\Livewire\Trip\PricingPage;
use App\Livewire\Trip\PaymentPage;
use App\Livewire\Trip\ReconcilePage;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Report\ReportIndex;
use App\Livewire\Report\TripFinancialDetail;

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

Route::get('packages', PackageIndex::class)->name('packages');
Route::get('packages/{package}', PackageDetail::class)->name('packages.show');

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

Route::middleware(['auth', 'permission:trip.view'])
    ->prefix('trips')->name('trips.')->group(function () {
        Route::get('/', TripIndex::class)->name('index');
        Route::get('create', TripForm::class)->name('create');
        Route::get('{trip}', TripShow::class)->name('show');
        Route::get('{trip}/edit', TripForm::class)->name('edit');
        Route::get('{trip}/pricing', PricingPage::class)->name('pricing');
        Route::get('{trip}/payments', PaymentPage::class)->name('payments');
        Route::get('{trip}/reconcile', ReconcilePage::class)->name('reconcile');
    });

// Laporan keuangan: butuh izin laporan dan izin keuangan. Pemeriksaan ulang di komponen/controller.
Route::middleware(['auth', 'permission:report.view'])->group(function () {
    Route::get('reports', ReportIndex::class)->name('reports');
    Route::get('reports/export/{report}', ReportExportController::class)->name('reports.export');
    Route::get('reports/{trip}', TripFinancialDetail::class)->name('reports.trip');
});

Route::middleware(['auth', 'permission:rules.manage'])->group(function () {
    Route::get('pricing-rules', PricingRuleIndex::class)->name('pricing-rules');
    Route::get('profit-rules', \App\Livewire\Profit\ProfitRuleIndex::class)->name('profit-rules');
});

require __DIR__.'/auth.php';