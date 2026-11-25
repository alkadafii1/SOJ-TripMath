<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $vendors = [
            // ========== HOTEL / AKOMODASI ==========
            [
                'name' => 'Dialoog Banyuwangi',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel bintang 5 tepi pantai, dekat Pelabuhan Ketapang. Harga mulai Rp 2.058.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Ketapang Indah Hotel',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel dengan pantai pribadi, view Selat Bali. Harga mulai Rp 706.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Aston Banyuwangi Hotel & Conference Center',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel bintang 4 dengan fasilitas conference. Harga mulai Rp 844.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Kokoon Hotel Banyuwangi',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel modern dengan rating 9.6. Harga mulai Rp 977.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'ILLIRA Hotel Banyuwangi',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel modern dengan spot instagramable, view Pulau Bali. Harga mulai Rp 740.000/malam [citation:2][citation:8].',
                'status' => 'active',
            ],
            [
                'name' => 'eL Hotel Banyuwangi',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel dengan rating 9.5. Harga mulai Rp 846.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Ijen Resort and Villas - The Hidden Paradise',
                'category' => 'resort',
                'contact' => '0333-XXXXXX',
                'notes' => 'Resort dekat Kawah Ijen dengan pemandangan alam. Harga mulai Rp 1.871.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Luminor Hotel Banyuwangi By WH',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel dengan rating 9.0. Harga mulai Rp 618.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Mirah Hotel',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel dengan rating 8.9. Harga mulai Rp 456.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'Hotel Mahkota Plengkung',
                'category' => 'hotel',
                'contact' => '0333-XXXXXX',
                'notes' => 'Hotel budget dengan rating 8.2. Harga mulai Rp 212.000/malam [citation:2].',
                'status' => 'active',
            ],
            [
                'name' => 'ReVive by Snooze',
                'category' => 'hostel',
                'contact' => '-',
                'notes' => 'Hostel dengan rating 9.6, cocok untuk backpacker [citation:8].',
                'status' => 'active',
            ],

            // ========== RENTAL KENDARAAN ==========
            [
                'name' => 'Mollyrentcar Banyuwangi',
                'category' => 'rental_kendaraan',
                'contact' => 'WhatsApp (booking online)',
                'notes' => 'Rental mobil sejak 2014, 50+ armada (Avanza, Innova, Fortuner, Alphard, Hiace, Elf, Bus). Layanan 24/7, full asuransi [citation:3].',
                'status' => 'active',
            ],
            [
                'name' => 'Campa Tour',
                'category' => 'rental_kendaraan',
                'contact' => '-',
                'notes' => 'Rental mobil + tour. Harga: Avanza Rp 750.000/12 jam, Innova Rp 850.000/12 jam, Hiace Rp 1.550.000/12 jam, Long Elf Rp 1.750.000/12 jam [citation:15].',
                'status' => 'active',
            ],
            [
                'name' => 'Yukbanyuwangi (Yukexperience Group)',
                'category' => 'rental_kendaraan',
                'contact' => '+62 81357623911',
                'notes' => 'Berdiri sejak 2014, melayani 10.400+ perjalanan. Rental Fortuner Rp 1.800.000/12 jam termasuk driver & BBM [citation:9].',
                'status' => 'active',
            ],

            // ========== TOUR GUIDE / TRAVEL AGENCY ==========
            [
                'name' => 'Osing Guide',
                'category' => 'tour_guide',
                'contact' => 'osingguide.com',
                'notes' => 'Platform digital pemandu wisata lokal Banyuwangi. Guide tersedia dengan keahlian fotografi, videografi, pilot drone. Dilengkapi sistem rating [citation:6].',
                'status' => 'active',
            ],
            [
                'name' => 'TripBanyuwangi',
                'category' => 'travel_agency',
                'contact' => '-',
                'notes' => 'Tour agency dengan rating 5.0 (206 review). Menyediakan open trip & private trip 3D2N, guide ramah, hasil foto bagus [citation:12].',
                'status' => 'active',
            ],

            // ========== UMKM / OLEH-OLEH ==========
            [
                'name' => 'Batik Godho',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM batik Banyuwangi, bagian dari kolaborasi Kanggi (keranjang oleh-oleh khas) [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Kopi Lego',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM kopi dari Desa Gombengsari, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Widya Handycraft',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM kerajinan dari Desa Gintangan, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Tukul Rizki Banyuwangi',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM produk sabun VCO dan wewangian, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Sri Mulyo',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM kudapan khas lokal dari Desa Sragi, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Ala Kuwung Suvenir',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM suvenir khas Banyuwangi, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],
            [
                'name' => 'Jamu Meneer Deles',
                'category' => 'oleh_oleh',
                'contact' => '-',
                'notes' => 'UMKM jamu tradisional, bagian dari kolaborasi Kanggi [citation:10].',
                'status' => 'active',
            ],

            // ========== KULINER / RESTORAN ==========
            [
                'name' => 'Kampung Bethek',
                'category' => 'kuliner',
                'contact' => '-',
                'notes' => 'Prasmanan all-you-can-eat dengan suasana kampung tradisional. Harga Rp 15.000/orang.',
                'status' => 'active',
            ],
            [
                'name' => 'Rawon Bik Ati',
                'category' => 'kuliner',
                'contact' => '-',
                'notes' => 'Rawon khas Banyuwangi dengan kuah hitam pekat. Estimasi Rp 35.000/orang.',
                'status' => 'active',
            ],
            [
                'name' => 'Srengenge Wetan',
                'category' => 'kuliner',
                'contact' => '-',
                'notes' => 'Restoran yang menyajikan Rujak Soto, kuliner ikonik Banyuwangi.',
                'status' => 'active',
            ],
            [
                'name' => 'Nasi Tempong Mbok Nah',
                'category' => 'kuliner',
                'contact' => '-',
                'notes' => 'Warung Nasi Tempong khas Banyuwangi. Estimasi Rp 35.000/orang.',
                'status' => 'active',
            ],

            // ========== CONTOH VENDOR TIDAK AKTIF ==========
            [
                'name' => 'Vendor Lama (Tidak Aktif)',
                'category' => 'hotel',
                'contact' => '-',
                'notes' => 'Vendor contoh yang sudah tidak bekerja sama.',
                'status' => 'inactive',
            ],
        ];

        foreach ($vendors as $vendor) {
            SeedDefaults::insertIfMissing('vendors', ['name' => $vendor['name']], [
                'name' => $vendor['name'],
                'category' => $vendor['category'],
                'contact' => $vendor['contact'],
                'notes' => $vendor['notes'],
                'status' => $vendor['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}