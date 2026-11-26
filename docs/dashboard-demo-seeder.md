# Seeder Demo Dashboard

`DashboardDemoSeeder` menambahkan data transaksi sintetis untuk mencoba dashboard operasional, ringkasan keuangan, laporan, dan audit log.

## Peringatan Database

Seeder memakai koneksi database yang sedang aktif. Jika aplikasi lokal terhubung ke Aiven, data demo ditulis ke Aiven, bukan ke database lokal terpisah. Label `[DEMO]` tidak mengecualikan data dari agregat: revenue, profit, jumlah trip, dan laporan akan ikut berubah.

Jalankan hanya pada database pengembangan/demo yang memang boleh menerima data sintetis. Seeder menolak environment selain `local` dan `testing`, tetapi environment lokal tidak menjamin database tujuannya bukan database bisnis. Tidak perlu mengubah `.env`.

Seeder ini dipanggil oleh `DatabaseSeeder` setelah data master dan paket pada environment `local/testing`. Jadi `php artisan db:seed` juga mengisi dashboard. Pada production, blok data demo dilewati.

## Prasyarat

- Migration aplikasi sudah terpasang.
- Seeder utama menyiapkan akun dan aturan awal jika belum ada; konfigurasi yang sudah ada tetap dipertahankan.
- Akun `owner@soj.test`, `staff@soj.test`, dan `partner@soj.test` tersedia, aktif, dan memiliki role yang sesuai.
- Aturan marketing dan bagi hasil yang berlaku saat ini valid. Seeder tidak membuat atau mengganti aturan bisnis.

Jika prasyarat belum terpenuhi, seeder berhenti dengan pesan yang menjelaskan masalahnya. Jangan memakai reset database untuk memperbaiki prasyarat pada database berisi data penting.

## Menjalankan

Setelah memastikan database tujuannya aman untuk data demo:

```powershell terminal
php artisan db:seed
```

Tidak perlu menjalankan `migrate:fresh` atau menghapus data yang ada. Jika hanya ingin menjalankan bagian dashboard setelah semua prasyarat siap, `php artisan db:seed --class=DashboardDemoSeeder` tetap tersedia.

## Data Yang Dibuat

- 12 trip berkode `DEMO-DASH-*`: satu untuk masing-masing 10 status aplikasi dan dua trip historis yang sudah direkonsiliasi.
- Dua master biaya khusus demo dan 24 snapshot komponen biaya trip.
- Contoh harga rekomendasi, negosiasi, permintaan persetujuan, diskon, dan harga final.
- Tujuh pembayaran simulasi, termasuk deposit dan pelunasan.
- Tiga snapshot rekonsiliasi dan enam bagian profit untuk Owner/Partner, dihitung melalui service aplikasi.
- Audit log dan contoh trip dengan biaya aktual belum lengkap, menunggu rekonsiliasi, minimum pax belum tercapai, serta pembayaran belum lunas.

Tanggal dibuat relatif terhadap saat pertama kali seeder dijalankan. Contoh rekonsiliasi bulan berjalan tetap masuk ringkasan pada hari pertama bulan tersebut. Contoh historis mengisi dua bulan sebelumnya untuk laporan tren.

## Mengecek Dashboard

Buka dashboard sebagai Owner untuk melihat ringkasan keuangan. Jadwal dan peringatan operasional mengikuti status trip. Akun `partner@soj.test` hanya melihat trip yang ditugaskan kepadanya; akun Staff tetap mengikuti izin keuangan yang berlaku.

Untuk tren historis, pilih periode laporan yang mencakup bulan berjalan dan dua bulan sebelumnya.

## Aman Dijalankan Ulang

Kode trip bersifat tetap. Trip yang sudah ada dilewati seluruhnya, termasuk yang dihapus secara soft delete. Seeder tidak menggandakan pembayaran/snapshot/bagi hasil, menimpa perubahan manual, atau menggeser tanggal demo lama agar terlihat baru.

Jika master demo telah diubah atau dinonaktifkan, seeder berhenti daripada menimpanya. Seluruh penambahan dibungkus transaksi sehingga kegagalan tidak meninggalkan sebagian data baru.

Data demo lama dapat keluar dari periode dashboard saat waktu berjalan. Gunakan filter periode yang sesuai; menjalankan ulang seeder bukan cara untuk memindahkan transaksi lama ke periode baru.

## Pengujian

`tests/Feature/DashboardDemoSeederTest.php` memeriksa isi dashboard, konsistensi angka keuangan, pengulangan tanpa duplikasi, penghormatan terhadap soft delete, rollback ketika ada konflik master, penolakan production, serta pergantian awal bulan.
