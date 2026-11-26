<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            OwnerSeeder::class,
            PricingRuleSeeder::class,
            ProfitRuleSeeder::class,
        ]);

        // Sample rates and accounts must never be installed on production implicitly.
        if (app()->environment(['local', 'testing'])) {
            $this->call([
                VendorSeeder::class,
                ActivitySeeder::class,
                CostItemSeeder::class,
                DestinationSeeder::class,
                HotelSeeder::class,
                MealSeeder::class,
                VehicleSeeder::class,
                PackageSeeder::class,
                DashboardDemoSeeder::class,
            ]);
        }
    }
}
