1. . Masuk ke Folder Project
bash

```cd nama-folder-project```

(Ganti nama-folder-project dengan nama folder yang barusan ter-clone, misal: cd SOJ-TripMath)
2. Install Dependensi PHP (Backend)

Laravel butuh library dari Composer.
bash

```composer install```

(Jika kamu belum punya vendor/ folder, perintah ini akan mengunduhnya).
3. Copy File Environment

Laravel menggunakan file .env untuk konfigurasi (koneksi database, dll). File ini biasanya tidak ikut ter-clone karena bersifat rahasia.
bash

```cp .env.example .env```

(Jika kamu menggunakan Windows Command Prompt dan cp error, gunakan copy .env.example .env)
4. Generate Application Key

Laravel membutuhkan key ini untuk enkripsi (session, cookies, dll).
bash

```php artisan key:generate```

5. Sesuaikan Konfigurasi Database (Manual)

Buka file .env di text editor (VS Code), lalu cari dan sesuaikan bagian ini dengan database lokalmu (misal pakai XAMPP/MySQL atau PostgreSQL):
env

```DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_kamu
DB_USERNAME=root
DB_PASSWORD=
```
Pastikan kamu sudah membuat database kosong di phpMyAdmin/pgAdmin dengan nama yang sama seperti DB_DATABASE di atas.
6. Install Dependensi Frontend (Node.js)

Untuk mengatur Tailwind CSS dan Alpine.js (yang dipakai di Livewire).
bash

```npm install```

(Atau npm ci jika kamu ingin mengikuti persis versi di package-lock.json)
7. Jalankan Migrasi Database (dan Seeder)

Ini akan membuat tabel-tabel di database dan mengisi data awal (jika ada).
bash

```php artisan migrate```

(Jika project menyertakan data dummy/seeder, gunakan: php artisan migrate --seed atau php artisan migrate:fresh --seed untuk me-reset ulang)
8. Build Aset Frontend
bash

```npm run build```

(Atau jika kamu sedang development dan ingin Tailwind/Vite otomatis meng-update saat file diubah, gunakan ```npm run dev```. Biarkan terminal ini berjalan).
9. Bersihkan Cache (Opsional tapi sangat disarankan)

Kadang setelah clone, Laravel menyimpan cache konfigurasi lama yang bikin error.
bash

```php artisan optimize:clear```

10. Jalankan Server Lokal

Buka terminal baru (biarkan terminal npm run dev tetap berjalan jika kamu memakainya), lalu jalankan:
bash

```php artisan serve```

Terminal akan menampilkan URL, biasanya http://127.0.0.1:8000. Buka URL tersebut di browser.