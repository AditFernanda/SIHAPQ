# Panduan Maintenance SIHAPQ

Dokumen ini berisi peta project dan perintah rutin agar SIHAPQ mudah dirawat setelah dipakai di lingkungan perusahaan.

## Struktur Utama

- `app/Http/Controllers`: logika halaman, form, filter, laporan, dan dashboard.
- `app/Models`: model database utama seperti pengguna, mesin, produk, PIC QC, hasil QC, dan jenis NG.
- `app/Support/QcStatus.php`: sumber utama status QC (`RP`, `SR`, `SC`, `NG`, `WAITING`). Jika ingin mengubah aturan status, mulai dari file ini.
- `resources/views`: tampilan Blade untuk role admin, Quality Control, supervisor, dan mesin.
- `routes/web.php`: semua route web aplikasi.
- `database/migrations`: riwayat perubahan skema database. Jangan hapus migration lama yang sudah pernah dipakai, buat migration baru untuk perubahan berikutnya.
- `database/seeders`: data awal aplikasi.
- `config/qc.php`: konfigurasi khusus SIHAPQ yang dibaca dari `.env`.
- `public/vendor/chartjs`: Chart.js lokal agar dashboard tetap jalan tanpa internet.

## Perintah Rutin Developer

Jalankan dari root project:

```bash
php artisan test
npm run build
vendor/bin/pint --test
php artisan qc:deployment-check
```

Atau gunakan Composer:

```bash
composer quality
composer deploy:check
composer deploy:cache
composer deploy:clear
```

Catatan: `composer deploy:check` memakai mode strict. Jika `.env` masih memakai password awal seperti `admin123`, command ini akan gagal sampai password production diganti.

## Alur Perubahan Aman

1. Ubah kode sesuai kebutuhan.
2. Jika mengubah tabel/kolom database, buat migration baru.
3. Jangan mengedit data production langsung dari database kecuali untuk recovery.
4. Jalankan `php artisan test`.
5. Jalankan `npm run build` bila mengubah CSS/JS/view yang bergantung asset.
6. Jalankan `php artisan qc:deployment-check` sebelum upload ke server.

## Catatan Database

- `users.role = qc_inspector` adalah role login aplikasi dan ditampilkan sebagai `Quality Control`.
- `qc_inspectors` adalah data PIC QC/petugas pemeriksa yang memakai PIN.
- Status `NG` (No Good) adalah hasil tidak lolos; `WAITING` dipakai saat keputusan QC belum final.
- Riwayat pengecekan QC sebaiknya tetap disimpan karena dipakai untuk laporan, dashboard, analisis NG, dan audit.
- Migration lama tetap dipertahankan walaupun ada tabel/kolom legacy, karena itu bagian dari urutan pembentukan database.

## Seeding Master Data (Part, PIC QC, Mesin)

Master data diimpor dari file CSV di `database/seeders/data/` agar mudah diperbarui tanpa mengubah kode:

| Seeder | File CSV | Kolom |
| --- | --- | --- |
| `MasterPartSeeder` | `list_part.csv` | No Part, Nama Part, Proses, Customer, Status |
| `PicQcSeeder` | `list_pic_qc.csv` | NIK, Nama, PIN (6 digit), Status |
| `MachineSeeder` | `data_mesin.csv` | kode mesin, nama mesin, Bagian |

- Cara pakai: edit CSV terkait, lalu jalankan `php artisan db:seed --class=NamaSeeder --force`.
- Semua seeder **idempoten** (`updateOrCreate`) — aman dijalankan ulang, tidak menggandakan data, dan memulihkan baris yang sebelumnya di-soft-delete.
- `MachineSeeder` otomatis membuat departemen baru (kolom Bagian) bila belum ada, dan membuatkan akun login mesin (role `akun_mesin`, username = kode mesin, password default dari `qc.machine_default_password`).
- `php artisan db:seed` (tanpa `--class`) menjalankan akun admin + ketiga seeder di atas, **kecuali saat testing** (di-skip agar test cepat dan tidak bentrok dengan fixture).

## Checklist Sebelum Deploy Perusahaan

- `.env` dibuat dari `.env.production.example`.
- `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` sesuai alamat server.
- Password database, admin awal, dan mesin awal sudah diganti dari nilai default.
- Document root web server diarahkan ke folder `public`.
- `composer install --no-dev --optimize-autoloader` sudah dijalankan.
- `npm run build` sudah menghasilkan `public/build`.
- `php artisan migrate --force` sudah dijalankan.
- `php artisan config:cache`, `route:cache`, dan `view:cache` sudah dijalankan.
- `php artisan qc:deployment-check --strict` lolos.
- Backup database dan folder `storage/app/backups` sudah punya lokasi salinan di luar server aplikasi.
