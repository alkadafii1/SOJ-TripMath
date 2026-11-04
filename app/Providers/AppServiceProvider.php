<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\CostItem;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Meal;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            Log::setDefaultDriver('stderr');
        }

        // Profit, margin, bagi hasil: hanya Owner atau yang diberi financial_visibility
        Gate::define('view-finance', fn (User $user) =>
            $user->hasRole('owner') || $user->financial_visibility
        );

        // Angka biaya pada trip: Staff menyusun estimasi, jadi perlu melihat biaya
        Gate::define('view-costs', fn (User $user) =>
            $user->hasRole('owner')
            || $user->financial_visibility
            || $user->can('trip.create')
        );

        // morphMap biasa, bukan enforceMorphMap, supaya Spatie tidak error
        Relation::morphMap([
            'hotel' => Hotel::class,
            'vehicle' => Vehicle::class,
            'activity' => Activity::class,
            'meal' => Meal::class,
            'destination' => Destination::class,
            'cost_item' => CostItem::class,
        ]);
    }
}