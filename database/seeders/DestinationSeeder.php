<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $destinations = [
            // ========== Wisata Alam / Gunung ==========
            [
                'name' => 'Kawah Ijen',
                'category' => 'Gunung & Vulkanik',
                'location' => 'Desa Tamansari, Kecamatan Licin, Banyuwangi',
                'standard_cost' => 20000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Kawah Ijen (Weekend/Libur)',
                'category' => 'Gunung & Vulkanik',
                'location' => 'Desa Tamansari, Kecamatan Licin, Banyuwangi',
                'standard_cost' => 30000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Taman Nasional Baluran',
                'category' => 'Taman Nasional / Savana',
                'location' => 'Situbondo-Banyuwangi (perbatasan)',
                'standard_cost' => 21000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Taman Nasional Baluran (Weekend/Libur)',
                'category' => 'Taman Nasional / Savana',
                'location' => 'Situbondo-Banyuwangi (perbatasan)',
                'standard_cost' => 31000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Taman Nasional Alas Purwo',
                'category' => 'Taman Nasional / Hutan',
                'location' => 'Kecamatan Tegaldlimo, Banyuwangi',
                'standard_cost' => 20000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Taman Nasional Alas Purwo (Weekend/Libur)',
                'category' => 'Taman Nasional / Hutan',
                'location' => 'Kecamatan Tegaldlimo, Banyuwangi',
                'standard_cost' => 30000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'De Djawatan Forest',
                'category' => 'Hutan / Wisata Alam',
                'location' => 'Desa Benculuk, Kecamatan Cluring, Banyuwangi',
                'standard_cost' => 6000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Kebun Teh Kalibendo',
                'category' => 'Perkebunan / Wisata Alam',
                'location' => 'Kampung Anyar, Kecamatan Glagah, Banyuwangi',
                'standard_cost' => 10000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Wisata Pantai & Bahari ==========
            [
                'name' => 'Pantai Pulau Merah',
                'category' => 'Pantai',
                'location' => 'Kecamatan Sempu, Banyuwangi',
                'standard_cost' => 10000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Pantai Marina Boom',
                'category' => 'Pantai / Waterfront',
                'location' => 'Kota Banyuwangi',
                'standard_cost' => 5000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Teluk Hijau (Green Bay)',
                'category' => 'Pantai / Teluk',
                'location' => 'Desa Sarongan, Kecamatan Pesanggaran, Banyuwangi',
                'standard_cost' => 5000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Teluk Hijau (Weekend/Libur)',
                'category' => 'Pantai / Teluk',
                'location' => 'Desa Sarongan, Kecamatan Pesanggaran, Banyuwangi',
                'standard_cost' => 7500,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Pantai Bangsring Underwater',
                'category' => 'Snorkeling / Konservasi',
                'location' => 'Kecamatan Wongsorejo, Banyuwangi',
                'standard_cost' => 10000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Pantai Muncar',
                'category' => 'Pantai / Pelabuhan',
                'location' => 'Kecamatan Muncar, Banyuwangi',
                'standard_cost' => 5000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Pantai Mustika & Pulau Bedil',
                'category' => 'Pantai / Gugusan Pulau',
                'location' => 'Kecamatan Pesanggaran, Banyuwangi',
                'standard_cost' => 10000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Wisata Budaya & Keluarga ==========
            [
                'name' => 'Desa Wisata Osing Kemiren',
                'category' => 'Budaya / Desa Adat',
                'location' => 'Kemiren, Kecamatan Glagah, Banyuwangi',
                'standard_cost' => 0, // Gratis masuk, biaya tergantung paket
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Banyuwangi Park',
                'category' => 'Taman Rekreasi / Keluarga',
                'location' => 'Kota Banyuwangi',
                'standard_cost' => 30000, // Mulai dari
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Taman Gandrung Terakota',
                'category' => 'Budaya / Seni',
                'location' => 'Kecamatan Licin, Banyuwangi',
                'standard_cost' => 15000,
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
        ];

        foreach ($destinations as $destination) {
            SeedDefaults::insertIfMissing('destinations', ['name' => $destination['name']], [
                'name' => $destination['name'],
                'category' => $destination['category'],
                'location' => $destination['location'],
                'standard_cost' => $destination['standard_cost'],
                'status' => $destination['status'],
                'valid_from' => $destination['valid_from'],
                'valid_until' => $destination['valid_until'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}