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
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () {
        // Do not resurrect a package deliberately soft-deleted by the owner.
        if (Package::onlyTrashed()->where('name', 'Open Trip 2D1N - Opsi B')->exists()) {
            return;
        }
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

        $dest = fn (string $name, string $category) => Destination::where('name', $name)->firstOrFail();
        $meal = fn (string $type) => Meal::where('meal_type', $type)->firstOrFail();

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
            [$dest('De Djawatan Forest', 'wisata alam'), 'destination', 1],
            [$dest('Pantai Mustika & Pulau Bedil', 'pantai'), 'destination', 1],
            [$dest('Pantai Pulau Merah', 'pantai'), 'destination', 1],
            [$meal('Lunch Box Standar'), 'meal', 1],
            [$meal('Dinner Box'), 'meal', 1],
            [$dest('Kawah Ijen', 'gunung'), 'destination', 2],
            [$dest('Taman Nasional Baluran', 'taman nasional'), 'destination', 2],
            [$meal('Breakfast Box (Nasi Goreng)'), 'meal', 2],
        ];

        $rows[] = [Hotel::where('name', 'Hotel Kokoon')->firstOrFail(), 'hotel', 1];
        $rows[] = [Vehicle::where('type', 'Hino RK8 Big Bus')->firstOrFail(), 'vehicle', 1];

        foreach ($rows as [$model, $type, $day]) {
            $version->items()->create([
                'item_type' => $type,
                'item_id' => $model->id,
                'quantity' => 1,
                'day_no' => $day,
            ]);
        }
        });
    }
}