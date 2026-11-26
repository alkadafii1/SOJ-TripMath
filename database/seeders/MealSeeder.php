<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $meals = [
            // ========== Makan Pagi / Breakfast ==========
            [
                'meal_type' => 'Breakfast Box (Nasi Goreng)',
                'cost_per_person' => 25000,
                'notes' => 'Paket sarapan praktis untuk trip pagi (Ijen/Baluran). Nasi goreng, telur, air mineral.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Breakfast Box (Roti & Buah)',
                'cost_per_person' => 20000,
                'notes' => 'Alternatif sarapan ringan: roti, buah, air mineral. Cocok untuk tamu yang tidak suka nasi.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Makan Siang / Lunch ==========
            [
                'meal_type' => 'Nasi Tempong (Kuliner Khas)',
                'cost_per_person' => 35000,
                'notes' => 'Nasi dengan sambal tempong khas Banyuwangi, sayur rebus, lauk pilihan (ayam/tahu/tempe). Estimasi harga warung lokal seperti Mbok Nah [citation:12].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Nasi Pecel Rawon',
                'cost_per_person' => 30000,
                'notes' => 'Perpaduan pecel dan rawon khas Banyuwangi. Harga referensi Rp 17.000–26.000 per porsi di warung lokal [citation:15].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Rujak Soto',
                'cost_per_person' => 30000,
                'notes' => 'Kuliner ikonik Banyuwangi: rujak sayur dengan kuah soto daging. Tersedia di restoran seperti Srengenge Wetan [citation:4].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Ayam Kesrut',
                'cost_per_person' => 35000,
                'notes' => 'Ayam kampung dengan kuah pedas asam gurih khas Osing. Populer di warung sekitar Licin/Ijen [citation:8].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Prasmanan Kampung Bethek',
                'cost_per_person' => 15000,
                'notes' => 'Makan prasmanan all-you-can-eat dengan suasana kampung tradisional. Harga sangat terjangkau, referensi Rp 15.000/orang [citation:3][citation:7].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Lunch Box Standar',
                'cost_per_person' => 25000,
                'notes' => 'Paket makan siang praktis: nasi, lauk, sayur, air mineral. Untuk rombongan dengan budget terbatas.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Makan Siang Restoran Lokal',
                'cost_per_person' => 40000,
                'notes' => 'Makan siang di restoran lokal Banyuwangi. Estimasi bujet wisatawan Rp 25.000–40.000/orang [citation:2][citation:10].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Makan Malam / Dinner ==========
            [
                'meal_type' => 'Makan Malam Restoran Lokal',
                'cost_per_person' => 45000,
                'notes' => 'Makan malam di restoran lokal. Estimasi bujet Rp 25.000–50.000/orang [citation:10].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Rawon Bik Ati (Makan Malam)',
                'cost_per_person' => 35000,
                'notes' => 'Rawon khas Banyuwangi dengan kuah hitam pekat dan daging empuk. Referensi bujet Rp 70.000 untuk 2 orang [citation:6].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Dinner Box',
                'cost_per_person' => 30000,
                'notes' => 'Paket makan malam praktis untuk rombongan yang tiba larut atau akan trip dini hari (Ijen midnight).',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Paket Makan (Multi-day) ==========
            [
                'meal_type' => 'Paket Makan 3 Hari (6x Makan)',
                'cost_per_person' => 200000,
                'notes' => 'Paket makan untuk trip 3D2N. Estimasi total bujet makan Rp 200.000–300.000/orang untuk 3 hari [citation:10].',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],
            [
                'meal_type' => 'Paket Makan 2 Hari (4x Makan)',
                'cost_per_person' => 140000,
                'notes' => 'Paket makan untuk trip 2D1N. Estimasi Rp 35.000 per waktu makan.',
                'status' => 'active',
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
            ],

            // ========== Contoh status tidak aktif ==========
            [
                'meal_type' => 'Prasmanan Premium (Maintenance)',
                'cost_per_person' => 75000,
                'notes' => 'Paket prasmanan premium dengan menu seafood. Sementara tidak tersedia karena vendor sedang renovasi.',
                'status' => 'inactive',
                'valid_from' => $now,
                'valid_until' => $now->copy()->subMonth(),
            ],
        ];

        foreach ($meals as $meal) {
            $vendorName = match ($meal['meal_type']) {
                'Prasmanan Kampung Bethek' => 'Kampung Bethek',
                'Rawon Bik Ati (Makan Malam)' => 'Rawon Bik Ati',
                'Rujak Soto' => 'Srengenge Wetan',
                default => null,
            };
            SeedDefaults::insertIfMissing('meals', ['meal_type' => $meal['meal_type']], [
                'vendor_id' => $vendorName === null ? null : DB::table('vendors')->where('name', $vendorName)->whereNull('deleted_at')->value('id'),
                'meal_type' => $meal['meal_type'],
                'cost_per_person' => $meal['cost_per_person'],
                'notes' => $meal['notes'],
                'status' => $meal['status'],
                'valid_from' => $meal['status'] === 'active' ? $meal['valid_from'] : $now->copy()->subYear(),
                'valid_until' => $meal['valid_until'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}