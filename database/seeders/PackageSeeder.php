<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Meal;
use App\Models\Package;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $package = Package::firstOrCreate(
            ['name' => 'Open Trip 2D1N - Opsi B'],
            [
                'trip_type' => 'open_trip',
                'description' => 'Banyuwangi: De Djawatan, Pantai Mustika, Pulau Merah, Kawah Ijen, dan TN Baluran.',
                'status' => 'active',
            ]
        );

        if ($package->versions()->exists()) {
            return; // jangan menimpa data yang sudah ada
        }

        $dest = fn (string $name, string $category) => Destination::firstOrCreate(
            ['name' => $name],
            ['category' => $category, 'location' => 'Banyuwangi', 'standard_cost' => 0, 'status' => 'active']
        );
        $meal = fn (string $type) => Meal::firstOrCreate(
            ['meal_type' => $type, 'vendor_id' => null],
            ['cost_per_person' => 0, 'status' => 'active']
        );

        $version = $package->versions()->create([
            'version_no' => 1,
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'change_log' => 'Versi awal',
            'itinerary' => "Hari 1: Penjemputan, De Djawatan, makan siang, Pantai Mustika, sunset di Pulau Merah, makan malam, check in hotel.\n"
                ."Hari 2: Dini hari menuju Pos Paltuding, trekking Kawah Ijen (blue fire dan sunrise), sarapan, TN Baluran (Savana Bekol dan Pantai Bama), kembali ke Banyuwangi kota.",
            'inclusions' => "Transportasi dan BBM\nGuide/fotografer\nTiket masuk dan parkir\nMakan 3x sesuai program\nAir mineral\nP3K\nDokumentasi foto\nMasker gas dan senter Kawah Ijen\nAkomodasi 1 malam (sesuai pilihan)",
            'exclusions' => "Tiket transportasi menuju/meninggalkan Banyuwangi\nSurat keterangan sehat untuk pendakian Ijen\nPengeluaran pribadi\nMakan dan minum di luar program\nTip guide dan driver\nLaundry",
        ]);

        $rows = [
            [$dest('De Djawatan', 'wisata alam'), 'destination', 1],
            [$dest('Pantai Mustika', 'pantai'), 'destination', 1],
            [$dest('Pulau Merah', 'pantai'), 'destination', 1],
            [$meal('lunch'), 'meal', 1],
            [$meal('dinner'), 'meal', 1],
            [$dest('Kawah Ijen', 'gunung'), 'destination', 2],
            [$dest('Taman Nasional Baluran', 'taman nasional'), 'destination', 2],
            [$meal('breakfast'), 'meal', 2],
        ];

        if ($hotel = Hotel::where('name', 'Hotel Kokoon')->first()) {
            $rows[] = [$hotel, 'hotel', 1];
        }
        if ($bus = Vehicle::where('type', 'Bus')->first()) {
            $rows[] = [$bus, 'vehicle', 1];
        }

        foreach ($rows as [$model, $type, $day]) {
            $version->items()->create([
                'item_type' => $type,
                'item_id' => $model->id,
                'quantity' => 1,
                'day_no' => $day,
            ]);
        }
    }
}