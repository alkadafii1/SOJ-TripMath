<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $activities = [
            // ========== Rafting / Arung Jeram ==========
            [
                'name' => 'Rafting Adventure Sungai Badeng (Songgon)',
                'standard_cost' => 165000,
                'cost_type' => 'per_person',
                'capacity' => 20,
                'duration_hours' => 2,
                'notes' => 'Rafting 1.5-2 jam di Sungai Badeng, Songgon. Termasuk guide, helm, safety jacket, makan 1x, snack, asuransi, tiket masuk. Harga WNA berbeda (Rp 250.000).',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Rafting Long Trip Sungai Badeng (Songgon)',
                'standard_cost' => 250000,
                'cost_type' => 'per_person',
                'capacity' => 20,
                'duration_hours' => 4,
                'notes' => 'Rafting rute terpanjang 3-3.5 jam. Termasuk guide, safety gear, makan 1x, snack 2x, asuransi, transport lokal. Harga WNA Rp 350.000.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Rafting Wonderful (Pemula/Keluarga)',
                'standard_cost' => 135000,
                'cost_type' => 'per_person',
                'capacity' => 20,
                'duration_hours' => 1,
                'notes' => 'Rafting jalur pendek cocok pemula dan keluarga. Termasuk guide, safety gear, makan 1x, snack. Harga WNA Rp 200.000.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Paket Rafting Banyuwangi (Transport + Rafting)',
                'standard_cost' => 390000,
                'cost_type' => 'per_person',
                'capacity' => 10,
                'duration_hours' => 6,
                'notes' => 'Paket lengkap: penjemputan, transport AC, rafting Songgon, hutan pinus, makan siang, snack. Harga per orang min. 10 pax. Untuk 4 pax Rp 510.000/orang.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Snorkeling / Wisata Bahari ==========
            [
                'name' => 'Snorkeling Bangsring Underwater',
                'standard_cost' => 100000,
                'cost_type' => 'per_person',
                'capacity' => 30,
                'duration_hours' => 3,
                'notes' => 'Snorkeling di Bangsring Underwater, Wongsorejo. Termasuk peralatan snorkeling, guide lokal, boat ke rumah apung. Estimasi harga, konfirmasi vendor untuk harga pasti.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Fullday Tour Baluran + Bangsring Underwater',
                'standard_cost' => 350000,
                'cost_type' => 'per_person',
                'capacity' => 10,
                'duration_hours' => 10,
                'notes' => 'Open trip Sabtu, min. 2 peserta. Termasuk transport AC, guide, makan siang, tiket masuk, snorkeling gear. Harga estimasi dari RM 102 (sekitar Rp 350.000).',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Explore Menjangan & Tabuhan Island',
                'standard_cost' => 330000,
                'cost_type' => 'per_person',
                'capacity' => 15,
                'duration_hours' => 8,
                'notes' => 'Snorkeling/diving di Pulau Menjangan & Tabuhan. Termasuk transport, boat transfer, snorkeling gear, makan siang. Harga estimasi dari RM 95 (sekitar Rp 330.000).',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Trekking / Tour Ijen ==========
            [
                'name' => 'Open Trip Kawah Ijen (1 Hari)',
                'standard_cost' => 180000,
                'cost_type' => 'per_person',
                'capacity' => 20,
                'duration_hours' => 12,
                'notes' => 'Open trip Kawah Ijen midnight. Termasuk transport, tiket masuk, guide, masker gas, senter, air mineral, snack. Harga WNA Rp 309.000.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'One Day Trip Explore Banyuwangi (Ijen Route)',
                'standard_cost' => 600000,
                'cost_type' => 'per_group',
                'capacity' => 5,
                'duration_hours' => 12,
                'notes' => 'Private trip 1 mobil (1-5 orang) rute Ijen. Termasuk transport, driver/guide. Harga per grup, bukan per orang.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'One Day Trip Explore Banyuwangi (Baluran Route)',
                'standard_cost' => 625000,
                'cost_type' => 'per_group',
                'capacity' => 5,
                'duration_hours' => 10,
                'notes' => 'Private trip 1 mobil (1-5 orang) rute Baluran. Termasuk transport, driver/guide.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Wisata Edukasi / Konservasi ==========
            [
                'name' => 'Turtle Observation Sukamade (2D1N)',
                'standard_cost' => 2100000,
                'cost_type' => 'per_person',
                'capacity' => 10,
                'duration_hours' => 48,
                'notes' => 'Paket 2 hari 1 malam observasi penyu di Pantai Sukamade. Termasuk transport, penginapan, guide, makan. Harga mulai dari Rp 2.100.000.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Banyuwangi Outing Package (Rafting + Outbound)',
                'standard_cost' => 2373000,
                'cost_type' => 'per_person',
                'capacity' => 20,
                'duration_hours' => 24,
                'notes' => 'Paket outing 2D1N: rafting, outbound di Bospro Songgon, akomodasi. Cocok untuk corporate/group.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Paket Kombinasi ==========
            [
                'name' => 'Djawatan - Green Bay - Red Island Tour',
                'standard_cost' => 1000000,
                'cost_type' => 'per_group',
                'capacity' => 5,
                'duration_hours' => 10,
                'notes' => 'Private trip 1 mobil (1-5 orang) ke De Djawatan, Teluk Hijau, Pulau Merah. Termasuk transport, driver/guide.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'name' => 'Paket Banyuwangi 3 Hari (Ijen + Menjangan)',
                'standard_cost' => 1500000,
                'cost_type' => 'per_person',
                'capacity' => 10,
                'duration_hours' => 72,
                'notes' => 'Paket 3D2N: Kawah Ijen, Pulau Menjangan, akomodasi, transport. Harga estimasi, konfirmasi vendor.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Contoh aktivitas tidak aktif ==========
            [
                'name' => 'Off-road Jeep Ijen (Maintenance)',
                'standard_cost' => 750000,
                'cost_type' => 'per_group',
                'capacity' => 6,
                'duration_hours' => 6,
                'notes' => 'Jeep tour ke Ijen, sementara tidak beroperasi karena perawatan kendaraan.',
                'status' => 'inactive',
                'valid_from' => $now,
                'valid_until' => $now->copy()->subMonth(),
            ],
        ];

        foreach ($activities as $activity) {
            DB::table('activities')->insert([
                'vendor_id' => null, // Sesuaikan jika ada vendor
                'name' => $activity['name'],
                'standard_cost' => $activity['standard_cost'],
                'cost_type' => $activity['cost_type'],
                'capacity' => $activity['capacity'],
                'duration_hours' => $activity['duration_hours'],
                'notes' => $activity['notes'],
                'status' => $activity['status'],
                'valid_from' => $activity['valid_from'],
                'valid_until' => $activity['valid_until'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}