<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $vehicles = [
            // ========== MPV / Keluarga ==========
            [
                'type' => 'Toyota Avanza',
                'capacity' => 7,
                'standard_cost' => 500000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV keluarga, cocok untuk city tour Banyuwangi',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota Innova Reborn',
                'capacity' => 7,
                'standard_cost' => 750000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV premium, nyaman untuk perjalanan jauh ke Ijen/Baluran',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota Innova Zenix',
                'capacity' => 7,
                'standard_cost' => 900000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV hybrid terbaru, lebih irit dan nyaman',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Mitsubishi Xpander',
                'capacity' => 7,
                'standard_cost' => 550000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV modern dengan kabin lega',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Honda Mobilio',
                'capacity' => 7,
                'standard_cost' => 450000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV ekonomis untuk keluarga kecil',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Suzuki Ertiga',
                'capacity' => 7,
                'standard_cost' => 425000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'MPV hemat bahan bakar',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== SUV ==========
            [
                'type' => 'Toyota Fortuner',
                'capacity' => 7,
                'standard_cost' => 1200000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'SUV tangguh untuk medan Ijen dan Baluran',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Mitsubishi Pajero Sport',
                'capacity' => 7,
                'standard_cost' => 1150000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'SUV premium, cocok untuk VIP tour',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota Rush',
                'capacity' => 7,
                'standard_cost' => 600000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'SUV compact, irit untuk perjalanan dalam kota',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Minibus / Van ==========
            [
                'type' => 'Toyota Hiace Commuter',
                'capacity' => 14,
                'standard_cost' => 1300000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Minibus untuk rombongan sedang, cocok tour Ijen',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota Hiace Premio',
                'capacity' => 14,
                'standard_cost' => 1600000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Minibus premium dengan kursi reclining',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Isuzu Elf Long',
                'capacity' => 19,
                'standard_cost' => 1800000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Minibus besar untuk rombongan wisata',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota HiAce Long',
                'capacity' => 15,
                'standard_cost' => 1500000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Minibus long body dengan bagasi luas',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Big Bus ==========
            [
                'type' => 'Hino RK8 Big Bus',
                'capacity' => 45,
                'standard_cost' => 4500000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Bus besar untuk rombongan wisata, termasuk driver + bahan bakar terpisah',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Mercedes-Benz OH 1526',
                'capacity' => 40,
                'standard_cost' => 5500000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Bus premium dengan AC dan kursi nyaman',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Jeep / Off-road (untuk Ijen & Baluran) ==========
            [
                'type' => 'Toyota Land Cruiser Hardtop',
                'capacity' => 6,
                'standard_cost' => 1500000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => true,
                'notes' => 'Jeep legendaris untuk off-road ke Kawah Ijen',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Suzuki Jimny 4x4',
                'capacity' => 4,
                'standard_cost' => 900000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Jeep compact untuk tracking ringan di Baluran',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Motor / Scooter (sewa mandiri) ==========
            [
                'type' => 'Honda Vario 125',
                'capacity' => 2,
                'standard_cost' => 75000,
                'cost_type' => 'per_day',
                'driver_included' => false,
                'fuel_included' => false,
                'notes' => 'Motor matic untuk sewa mandiri wisatawan',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Yamaha NMAX 155',
                'capacity' => 2,
                'standard_cost' => 125000,
                'cost_type' => 'per_day',
                'driver_included' => false,
                'fuel_included' => false,
                'notes' => 'Motor matic premium untuk touring',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Airport / Station Transfer ==========
            [
                'type' => 'Toyota Camry (Executive)',
                'capacity' => 3,
                'standard_cost' => 350000,
                'cost_type' => 'per_trip',
                'driver_included' => true,
                'fuel_included' => true,
                'notes' => 'Sedan eksekutif untuk transfer bandara/stasiun',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'type' => 'Toyota Alphard',
                'capacity' => 6,
                'standard_cost' => 850000,
                'cost_type' => 'per_trip',
                'driver_included' => true,
                'fuel_included' => true,
                'notes' => 'MPV luxury untuk VIP transfer',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Contoh kendaraan tidak aktif ==========
            [
                'type' => 'Toyota Kijang Innova Lama',
                'capacity' => 7,
                'standard_cost' => 400000,
                'cost_type' => 'per_day',
                'driver_included' => true,
                'fuel_included' => false,
                'notes' => 'Kendaraan dalam perbaikan',
                'status' => 'maintenance',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonth(),
            ],
        ];

        foreach ($vehicles as $vehicle) {
            SeedDefaults::insertIfMissing('vehicles', ['type' => $vehicle['type']], [
                'vendor_id' => null, // Sesuaikan jika ada vendor
                'type' => $vehicle['type'],
                'capacity' => $vehicle['capacity'],
                'standard_cost' => $vehicle['standard_cost'],
                'cost_type' => $vehicle['cost_type'],
                'driver_included' => $vehicle['driver_included'],
                'fuel_included' => $vehicle['fuel_included'],
                'notes' => $vehicle['notes'],
                'status' => $vehicle['status'] === 'maintenance' ? 'inactive' : $vehicle['status'],
                'valid_from' => $vehicle['valid_from'],
                'valid_until' => $vehicle['valid_until'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}