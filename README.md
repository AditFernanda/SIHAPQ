# SIHAPQ - Sistem Informasi Hasil Pengecekan Quality

Sistem internal PT RMI untuk mencatat hasil pengecekan quality produksi, menampilkan status hasil QC ke akun mesin, dan menyediakan rekap untuk Quality Control, supervisor, dan admin.

> Dipakai di lingkungan **pabrik internal (closed network)**. Tidak diakses dari publik.

---

## Daftar Isi

1. [Stack Teknologi](#stack-teknologi)
2. [Struktur Direktori Penting](#struktur-direktori-penting)
3. [Peran & Hak Akses](#peran--hak-akses)
4. [Konsep Domain Inti](#konsep-domain-inti)
5. [Setup Lokal (XAMPP + MySQL)](#setup-lokal-xampp--mysql)
6. [Perintah Penting](#perintah-penting)
7. [Troubleshooting Umum](#troubleshooting-umum)
8. [Catatan Maintenance](#catatan-maintenance)

---

## Stack Teknologi

| Komponen           | Versi / Tools                              |
| ------------------ | ------------------------------------------ |
| Framework backend  | Laravel 12                                 |
| PHP runtime        | 8.2 atau lebih baru                        |
| Database           | MySQL/MariaDB                              |
| Framework CSS      | Tailwind CSS v4                            |
| Build tool         | Vite                                       |
| Icon set           | Tabler Icons (`ti ti-*`)                   |
| Template engine    | Blade (komponen `<x-*>`)                   |
| Autentikasi        | Kustom berbasis sesi, **bukan** `Auth` bawaan Laravel |

---

## Struktur Direktori Penting

```
app/
├── Console/Commands/
│   ├── BackupDatabase.php          # Backup database manual / scheduler
│   ├── BenchmarkPerformance.php    # Ukur waktu respons pencarian & dashboard
│   ├── CheckDeploymentReadiness.php # Validasi konfigurasi sebelum deploy
│   ├── SeedBenchmarkData.php       # Generator data uji berskala besar
│   └── SyncMachineAccounts.php     # Sinkron akun mesin dengan master mesin
├── Http/
│   ├── Controllers/                # Satu controller per area (Admin*, Qc*, Auth, Dashboard)
│   └── Middleware/
│       ├── AddSecurityHeaders.php  # Header keamanan dasar untuk web internal
│       └── EnsureUserRole.php      # Gate akses by role: role:admin,supervisor,...
├── Models/                         # User, Machine, Product, Department, QcInspection, QcInspector, ActivityLog
├── Providers/
└── Support/
    └── QcStatus.php                # Kamus status RP/SR/SC/NG/WAITING (sumber tunggal)

database/
├── migrations/                     # Skema tabel (urutan kronologis)
└── seeders/                        # Data awal (akun admin)

lang/
└── id/validation.php               # Pesan validasi bahasa Indonesia

resources/
├── css/app.css                     # Sidebar drawer + utility kustom Tailwind 4
├── js/app.js                       # Init sidebar, confirm modal, validasi browser, auto-dismiss flash
└── views/
    ├── admin/                      # Halaman admin
    ├── qc/                         # Halaman PIC QC, laporan, dan template export
    ├── supervisor/                 # Halaman supervisor
    ├── mesin/                      # Halaman akun mesin
    ├── auth/                       # Masuk, ganti kata sandi
    └── components/                 # layouts/app, sidebar, data-table, search-form, dll

routes/web.php                      # Semua route web (group per role)
```

---

## Peran & Hak Akses

Sistem punya **4 peran**, diatur lewat kolom `users.role` dan dikunci oleh middleware `EnsureUserRole`:

| Peran           | Akses                                                                                          |
| --------------- | ---------------------------------------------------------------------------------------------- |
| `admin`         | Kelola pengguna, master mesin, bagian, part, jenis NG, PIC QC, reset kata sandi, dan riwayat aktivitas. |
| `supervisor`    | Dasbor supervisor, tren NG, mesin dengan NG terbanyak, lihat hasil QC, laporan, Simpan PDF, dan Unduh Excel. |
| `qc_inspector`  | Ditampilkan sebagai **Quality Control**. Input hasil QC, lihat hasil semua mesin, laporan, dan export. |
| `akun_mesin`    | Lihat dashboard mesin, status hasil QC keseluruhan mesin, dan riwayat hasil QC semua mesin untuk komputer bersama. |

Satu pengguna yang masuk akan diarahkan ke dasbor sesuai perannya (`AuthController::homeForRole`).

---

## Konsep Domain Inti

Bagian ini menjelaskan aturan utama yang dipakai aplikasi.

### 1. Hari Operasional QC vs Hari Kalender

QC bekerja per shift. Cut-off "hari operasional" default adalah **07:00 pagi** - bukan tengah malam. Nilainya bisa diubah lewat `QC_OPERATIONAL_DAY_START` di `.env`. Implementasi:

- Konfigurasi `config/qc.php`.
- `currentOperationalDate()` -> jika sekarang < 07:00, maka tanggal operasional = kemarin.
- Scope `forOperationalDay()` & `forOperationalDateRange()` digunakan **di semua filter laporan** supaya rekap shift malam tidak terpotong.

**Implikasi:** input hari berjalan mengikuti hari operasional QC, bukan tanggal kalender murni.

### 2. Status Hasil QC

| Kode | Arti                       | Lolos? |
| ---- | -------------------------- | ------ |
| RP   | Running Process            | Ya     |
| SR   | Special Request            | Ya     |
| SC   | Special Control            | Ya     |
| NG   | No Good                    | Tidak  |
| WAITING | Menunggu Konfirmasi     | Belum final |

RP/SR/SC dihitung lolos; `NG` (No Good) adalah hasil yang tidak lolos. `WAITING` dipakai saat Quality Control belum bisa memberi keputusan final karena butuh informasi tambahan. Aturan status dipusatkan di `App\Support\QcStatus` (satu-satunya sumber definisi kode, label, dan aturan lolos/akhir).

### 3. Input Hasil QC

Form input QC dibuat untuk data master yang jumlahnya banyak.

- Mesin dipilih lewat combobox pencarian berdasarkan kode mesin, nama mesin, atau bagian.
- Part dipilih lewat combobox pencarian berdasarkan no part, nama part, proses, atau customer.
- Nilai yang disimpan tetap `machine_id` dan `product_id`, bukan teks bebas.
- Jika hasil cek `NG`, PIC QC wajib memilih minimal satu **Jenis NG** dari master data.
- Satu hasil NG bisa memiliki lebih dari satu jenis NG; tiap jenis dihitung masing-masing di Histogram Pareto supervisor.
- Jika keputusan belum final, PIC QC dapat menyimpan status `WAITING` dengan keterangan alasan menunggu.
- Jika pengguna mengetik manual tanpa memilih data master, browser menampilkan pesan validasi bahasa Indonesia.

### 3a. Master Jenis NG

Jenis NG dikelola dari menu admin **Jenis NG**. Data ini dipakai oleh:

- Form input QC saat status `NG`.
- Histogram Pareto Jenis NG di dashboard supervisor.
- Kolom Jenis NG di hasil QC, laporan web, PDF/print, dan Excel.

Jenis NG yang sudah dipakai riwayat QC akan dinonaktifkan saat dihapus, bukan dihapus permanen, supaya histori tetap utuh.

### 4. Akun Mesin Otomatis

Setiap baris di tabel `machines` **otomatis** punya satu user `role=akun_mesin` di tabel `users`, relasi via pivot `user_machines`.

- Username akun = `machine_code` (contoh: `60T-4`).
- Kata sandi bawaan diatur dari `QC_MACHINE_DEFAULT_PASSWORD`.
- Buat, edit, atau hapus mesin akan ikut menyinkronkan akun mesin.
- Akun mesin dikelola dari menu Master Mesin, bukan dari Kelola Pengguna.
- Kata sandi akun mesin diatur ulang oleh admin dari Master Mesin.
- Akun mesin bisa membuka **Hasil QC Semua Mesin** agar komputer bersama tetap dapat dipakai lintas area.
- Akun mesin tidak perlu melakukan konfirmasi hasil; dashboard mesin bersifat monitoring hasil QC.

Bila pernah ada data mesin di-insert manual tanpa user counterpart, jalankan: `php artisan machines:sync-accounts`.

### 5. Backup Database

Backup manual:

```bash
php artisan qc:backup-database
```

Backup disimpan ke `storage/app/backups` secara default dan dijadwalkan harian pukul 23:00 lewat scheduler Laravel.

### 6. Verifikasi PIN PIC QC

Setiap penyimpanan hasil QC membutuhkan PIN 6 digit milik PIC QC yang dipilih.

- PIN disimpan **hash bcrypt** (lihat `QcInspector::setPinAttribute` + `verifyPin`).
- Tidak ada unique constraint di kolom PIN; verifikasi tetap dipasangkan dengan PIC QC yang dipilih.
- Saat mengubah data PIC QC, mengosongkan field PIN berarti PIN tidak berubah.

### 7. Aturan Kata Sandi

- Pengguna admin, supervisor, dan PIC QC bisa mengganti kata sandi sendiri dari menu Ganti Kata Sandi.
- Ganti kata sandi sendiri wajib memasukkan kata sandi lama.
- Admin bisa reset kata sandi pengguna lain dari Kelola Pengguna.
- Admin tidak bisa reset kata sandi dirinya sendiri dari Kelola Pengguna. Untuk akun sendiri, gunakan menu Ganti Kata Sandi.
- Kata sandi akun mesin direset dari Master Mesin.

### 8. Soft Delete + Status Aktif/Nonaktif

Untuk menjaga **integritas histori**:

- Tabel `users`, `machines`, `products`, `departments`, `qc_inspectors`, `qc_inspections` pakai `SoftDeletes`.
- Data master yang sudah dipakai histori QC akan dinonaktifkan, bukan dihapus permanen.
- PIC QC yang dihapus tetap memiliki riwayat QC utuh karena penghapusan normal memakai soft delete.

**Aturan praktis:** filter aktif (`->where('status', 'aktif')`) dipakai di dropdown pilihan & dashboard. Filter histori tetap menampilkan baris nonaktif.

### 9. Activity Log

Tabel `activity_logs` mencatat aksi administratif penting seperti pengelolaan master data, perubahan data pengguna, reset kata sandi, dan aktivitas master data. Tujuan utama: jejak audit, bukan log debug.

Field penting: `user_id`, `activity`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`.

### 10. Custom Session Auth

Model `User` **tidak extend** `Illuminate\Foundation\Auth\User`. Proses masuk dilakukan manual di `AuthController::login`:

1. Cari pengguna `username + status=aktif`.
2. `Hash::check()` kata sandi.
3. Simpan `user_id`, `user_name`, `user_role` ke session.
4. `EnsureUserRole` middleware membaca `session('user_role')` di setiap request.

Gunakan `session('user_id')` lalu `User::find(...)` untuk mengambil user aktif.

### 11. Keamanan Web Internal

Middleware `AddSecurityHeaders` menambahkan header keamanan dasar seperti `X-Frame-Options`, `X-Content-Type-Options`, dan `Referrer-Policy`. Aplikasi tetap diasumsikan berjalan di jaringan internal perusahaan, tetapi `APP_DEBUG=false` wajib dipakai di production.

### 12. Performa Laporan

Laporan dan dashboard memakai filter tanggal operasional, status, mesin, produk, dan PIC QC. Migration `2026_06_03_000001_add_reporting_indexes.php` menambahkan index gabungan untuk pola query tersebut.

### 13. Auto Refresh Monitoring

Halaman monitoring memakai auto refresh ringan:

- Dasbor akun mesin: 30 detik.
- Hasil QC akun mesin: 30 detik.
- Dasbor supervisor: 60 detik.

Auto refresh berhenti saat tab browser tidak aktif atau saat pengguna sedang fokus di input/select/textarea, sehingga tidak mengganggu filter.
Cache agregasi dashboard supervisor direkomendasikan `QC_DASHBOARD_CACHE_TTL=60`, selaras dengan auto refresh supervisor 60 detik.

---

## Setup Lokal (XAMPP + MySQL)

> Asumsi: macOS / Linux dengan XAMPP terinstall di `/Applications/XAMPP` (Mac). Sesuaikan path untuk OS lain.

### 1. Clone & install dependencies

```bash
git clone <repo-url> sihapq
cd sihapq

# Jika memakai XAMPP, pastikan Composer dijalankan dengan PHP CLI XAMPP
# supaya versi PHP dan ekstensi MySQL sesuai.
/Applications/XAMPP/xamppfiles/bin/php /opt/homebrew/bin/composer install

# Frontend
npm install
```

### 2. Setup database

Buka phpMyAdmin XAMPP, buat database `qc_pt_rmi` (utf8mb4_unicode_ci).

### 3. Setup `.env`

```bash
cp .env.example .env
/Applications/XAMPP/xamppfiles/bin/php artisan key:generate
```

Edit `.env`:

```env
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qc_pt_rmi
DB_USERNAME=root
DB_PASSWORD=
DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock

QC_ADMIN_DEFAULT_PASSWORD=admin123
QC_INSPECTOR_DEFAULT_PIN=654321
QC_MACHINE_DEFAULT_PASSWORD=mesin123
```

> `DB_SOCKET` penting di macOS untuk memastikan koneksi ke MySQL XAMPP (port 3307 internal), bukan MySQL Homebrew.
> Untuk produksi, ubah semua nilai bawaan kata sandi/PIN di atas sebelum menjalankan seeder.

### 4. Migrasi & seed

```bash
/Applications/XAMPP/xamppfiles/bin/php artisan migrate --seed
```

Akun default setelah seed:

| Nama pengguna | Kata sandi | Peran        |
| -------- | ---------- | ------------ |
| admin    | sesuai `QC_ADMIN_DEFAULT_PASSWORD` | admin |

(Ubah segera setelah masuk pertama kali.)

Seeder hanya membuat akun admin awal. Data PIC QC dibuat dari menu admin agar data awal produksi tetap bersih.

### 5. Build asset & jalankan

```bash
npm run build
/Applications/XAMPP/xamppfiles/bin/php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Perintah Penting

```bash
# Migrasi
php artisan migrate                 # apply migrations baru
php artisan migrate:fresh --seed    # reset DB + seed (HATI-HATI: hapus data)

# Akun mesin
php artisan machines:sync-accounts  # buat / sinkron / hapus akun mesin yatim

# Cache
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Frontend
npm run dev     # watch mode (development)
npm run build   # production build (output ke public/build/)

# Test
composer install # tanpa --no-dev agar phpunit tersedia
composer test
composer quality

# Backup
php artisan qc:backup-database

# Cek kesiapan deploy lokal/perusahaan
php artisan qc:deployment-check
php artisan qc:deployment-check --strict
composer deploy:check
composer deploy:cache
composer deploy:clear

# Data uji performa untuk skripsi/development
php artisan qc:seed-benchmark --count=500 --machines=20 --products=60 --inspectors=10 --days=120 --fresh
php artisan qc:benchmark --repeat=3
```

---

## Troubleshooting Umum

### 1. `vendor/` minta PHP 8.5 tapi sistem PHP lebih tua

Composer di-run dengan PHP system, bukan PHP XAMPP. Ulangi install pakai PHP XAMPP:

```bash
/Applications/XAMPP/xamppfiles/bin/php /opt/homebrew/bin/composer install
```

### 2. Koneksi MySQL "connection refused"

Pastikan MySQL XAMPP jalan (XAMPP Control -> Manage Servers). Verifikasi socket:

```bash
ls /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
```

Bila path beda, sesuaikan `DB_SOCKET` di `.env`.

### 3. `APP_KEY` kosong

```bash
php artisan key:generate
```

### 4. Setelah masuk langsung diarahkan ke /login lagi

Cek session driver di `.env` (`SESSION_DRIVER=file`) dan permission folder `storage/framework/sessions`.

### 5. Hasil QC tidak muncul di laporan hari ini

Cek jam input. Bila inspeksi disimpan sebelum jam cut-off hari operasional, masuk ke "hari operasional kemarin". Lihat [Hari Operasional QC](#1-hari-operasional-qc-vs-hari-kalender).

### 6. Akun mesin tidak bisa masuk

- Cek `users.status = 'aktif'`.
- Cek apakah ada baris di `user_machines` yang menghubungkan user dengan machine.
- Jalankan `php artisan machines:sync-accounts` untuk perbaikan otomatis.

---

## Catatan Pemeliharaan

- **Komentar di kode:** gunakan PHPDoc (`/** ... */`) di method publik, dan komentar inline hanya jika **alasan (why)** tidak jelas dari nama variabel. Hindari komentar yang sekadar menarasikan baris di bawahnya.
- **Migration:** jangan edit migration lama. Buat migration baru bila perlu mengubah skema produksi.
- **Tambah peran baru:** perbarui `EnsureUserRole`, `AuthController::homeForRole`, tambah grup route di `routes/web.php`, dan buat migration baru untuk memperbarui enum kolom `users.role`.
- **Tambah status hasil QC baru:** update `App\Support\QcStatus`, validasi input bila perlu, tampilan pilihan status, dashboard counter, test, dan dokumentasi di atas.
- **Kredensial bawaan:** jangan gunakan `admin123`, `654321`, atau `mesin123` di produksi. Tetapkan nilai aman di `.env` sebelum `php artisan migrate --seed`.
- **Cache lokal:** setelah ubah route/config/view, jalankan `php artisan optimize:clear` bila tampilan atau route terasa tidak mengikuti kode terbaru.

### Konvensi & pola yang dipakai

Ikuti pola yang sudah ada agar konsisten:

- **Satu controller per area** (`Admin*`, `Qc*`, `Auth`, `Dashboard`). Method standar CRUD: `index`, `store`, `update`, `destroy`.
- **Otorisasi** lewat middleware `role:...` di route, bukan dicek manual di controller.
- **Validasi** memakai `$request->validate([...])` dengan pesan Bahasa Indonesia dari `lang/id/validation.php`. Aturan kata sandi memakai `Password::defaults()` (lihat `AppServiceProvider`).
- **Audit aksi** memakai trait `App\Traits\LogsActivity` (`$this->logActivity(...)`).
- **Identitas perusahaan** (nama, singkatan, domain email) ambil dari `config('qc.*')`, jangan hardcode. Email internal user dibuat lewat `User::internalEmail($username)`.
- **Status QC** selalu lewat `App\Support\QcStatus`, jangan menulis literal `'RP'`/`'NG'`/`'WAITING'` tersebar.
- **Warna & tipografi** lewat design token di `resources/css/app.css` (`--color-*`). Ganti warna cukup di token, jangan hardcode hex di view.

### Resep: menambah master data baru (mis. "Supplier")

1. Buat migration tabel + model (`SoftDeletes` + `$fillable`).
2. Buat `AdminSupplierController` mengikuti pola `AdminProductController` (index/store/update/destroy + `LogsActivity`).
3. Daftarkan route di grup `role:admin` pada `routes/web.php`.
4. Tambah menu di `resources/views/components/sidebar.blade.php`.
5. Buat view memakai komponen `<x-data-table>`, `<x-search-form>`, dan `<x-modal>`.
6. Tambah label atribut Indonesia di `lang/id/validation.php` bila ada field baru.
7. Tambah test di `tests/Feature` (lihat `AdminRoleTest`), lalu jalankan `composer test` & `vendor/bin/pint`.
