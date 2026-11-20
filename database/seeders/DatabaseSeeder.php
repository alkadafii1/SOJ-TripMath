<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            RolePermissionSeeder::class,
            OwnerSeeder::class,
            ActivitySeeder::class,
            CostItemSeeder::class,
            DestinationSeeder::class,
            HotelSeeder::class,
            MealSeeder::class,
            PackageSeeder::class,
            PricingRuleSeeder::class,
            VehicleSeeder::class,
            VendorSeeder::class,
        ]);
    }
}
