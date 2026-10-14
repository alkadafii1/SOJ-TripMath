<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * PENTING: semua angka tarif di sini hanya PLACEHOLDER (0).
     * Isi tarif asli lewat halaman Data Master; perubahannya masuk audit log.
     */
    public function run(): void
    {
        $kokoon = Vendor::firstOrCreate(
            ['name' => 'Hotel Kokoon'],
            ['category' => 'hotel', 'status' => 'active']
        );
        $bus = Vendor::firstOrCreate(
            ['name' => 'Vendor Bus (GANTI nama asli)'],
            ['category' => 'transport', 'status' => 'active']
        );

        Hotel::firstOrCreate(
            ['name' => 'Hotel Kokoon', 'room_type' => 'Standard'],
            [
                'vendor_id' => $kokoon->id,
                'standard_rate' => 0,
                'occupancy' => 2,
                'cost_type' => 'per_room',
                'status' => 'active',
            ]
        );

        Vehicle::firstOrCreate(
            ['type' => 'Bus'],
            [
                'vendor_id' => $bus->id,
                'capacity' => 30, 
                'standard_cost' => 0,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'status' => 'active',
            ]
        );

        foreach ([
            ['De Djawatan', 'wisata alam', 'Banyuwangi'],
            ['Kawah Ijen', 'gunung', 'Banyuwangi'],
        ] as [$name, $category, $location]) {
            Destination::firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'location' => $location, 'standard_cost' => 0, 'status' => 'active']
            );
        }
    }
}