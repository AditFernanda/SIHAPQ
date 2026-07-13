# Peta Kode & Panduan Perawatan — SIHAPQ

> Dokumen ini bukan pengganti README/DEPLOYMENT/MAINTENANCE, tapi **peta cepat**:
> "bagian mana mengerjakan apa" dan "kalau mau ubah X, buka file mana".
> Dibuat agar aplikasi mudah dirawat di masa depan (termasuk oleh diri sendiri
> beberapa bulan lagi).

---

## 1. Gambaran Singkat

SIHAPQ = Sistem Inspeksi & Monitoring Quality Control untuk manufaktur.

- **Framework**: Laravel 12 (PHP 8.2+), MySQL, Tailwind CSS v4, Chart.js, DomPDF, PhpSpreadsheet.
- **4 peran**: `admin`, `supervisor`, `qc_inspector`, `akun_mesin`.
- **Status hasil QC**: RP, NG, SC, SR, Waiting (lihat `app/Support/QcStatus.php`).
- **Kebaruan (novelty) skripsi**: fitur **Akun Mesin** — layar di area produksi
  yang login pakai kode mesin dan menampilkan hasil QC untuk mesin itu saja,
  auto-refresh, tanpa operator perlu ke area QC.

---

## 2. Mana Bawaan Laravel, Mana Buatan Sendiri

**Tidak perlu dijelaskan (bawaan framework):** `vendor/`, `node_modules/`,
`bootstrap/providers.php`, `bootstrap/cache/`, `config/*` **kecuali** `config/qc.php`,
`app/Http/Controllers/Controller.php`, `app/Providers/AppServiceProvider.php`,
`artisan`, `public/index.php`, `vite.config.js`, `phpunit.xml`, `tests/TestCase.php`.

**Bawaan tapi dimodifikasi (paham bagian yang diubah saja):**
`bootstrap/app.php` (daftar middleware), `routes/web.php` (isi rute),
`routes/console.php` (jadwal backup), `app/Models/User.php` (relasi tambahan).

**Buatan sendiri (inti skripsi — wajib dikuasai):** semua sisanya di `app/`,
`resources/views/`, `database/migrations/`, dan `config/qc.php`.

> Cek mandiri kapan saja: `git log --diff-filter=A --format= --name-only | sort -u`

---

## 3. Alur Data Utama (ikuti urutannya untuk memahami sistem)

### a. Login & otorisasi
1. `routes/web.php` → `AuthController@login`.
2. `AuthController` cek kredensial (+ throttle 5x/60d), simpan `user_id`,
   `user_role` ke **session** (auth berbasis session, bukan guard Laravel).
3. Tiap request ke route terproteksi lewat middleware **`role`**
   (`app/Http/Middleware/EnsureUserRole.php`) yang mencocokkan ulang peran ke DB.

### b. Input hasil QC (jantung aplikasi)
1. `GET /qc/input` → `QcInspectionController@createWeb` → view `resources/views/qc/input.blade.php`.
2. QC isi form → JavaScript di view memvalidasi & membuka **modal PIN**.
3. `POST /qc/input` → `QcInspectionController@storeWeb`:
   - `validateWebInput()` — validasi + verifikasi **PIN PIC QC** (throttled) + cek mesin punya akun aktif.
   - simpan `QcInspection` dalam transaksi, sinkron jenis NG (pivot), catat aktivitas.
4. Hasil langsung tampil di dashboard **Akun Mesin** mesin terkait.

### c. Dashboard (per peran)
- `DashboardController` punya 1 method per peran: `admin()`, `qc()`, `supervisor()`, `mesin()`.
- `supervisor()` paling berat (Pareto, tren, top part/mesin) → hasilnya **di-cache**
  (lihat `config/qc.php` → `dashboard_cache_ttl`).

### d. Laporan & ekspor
- `QcReportController`: `index()` (tampil + paginasi), `exportPdf()` (pratinjau lalu unduh),
  `exportExcel()`. Ketiganya pakai filter yang sama (`applyFilters`).

### e. "Hari operasional" (konsep penting)
- Hari QC = **07:00 sampai 07:00** hari berikutnya (mengikuti shift pabrik).
- Logika di `app/Models/QcInspection.php`: `currentOperationalDate()`,
  `scopeForOperationalDay()`, `scopeForOperationalDateRange()`.

---

## 4. Peta File — Siapa Mengerjakan Apa

### Controllers (`app/Http/Controllers/`)
| File | Tanggung jawab |
|------|----------------|
| `AuthController` | Login (throttle), logout, arahkan sesuai peran |
| `PasswordController` | Ganti kata sandi sendiri |
| `QcInspectionController` | **Input hasil QC** + selesaikan status Waiting |
| `QcResultController` | Daftar hasil QC (dipakai QC/supervisor/akun mesin) |
| `QcReportController` | Laporan + ekspor PDF & Excel |
| `DashboardController` | Dashboard 4 peran + agregasi grafik |
| `Admin*Controller` (7) | CRUD master data (user, mesin, departemen, produk, jenis NG, anggota QC, log) |

### Models (`app/Models/`)
| File | Tabel | Catatan |
|------|-------|---------|
| `User` | users | akun login 4 peran |
| `QcInspection` | qc_inspections | **pusat data**; status, waktu, jenis NG, search_index |
| `Machine` | machines | mesin; relasi ke departemen & akun mesin |
| `Product` | products | part/produk |
| `Department` | departments | bagian (Press 1/2, Cutting, Injection) |
| `QcInspector` | qc_inspectors | PIC QC; **PIN ter-hash** (`verifyPin`) |
| `QcNgType` | qc_ng_types | master jenis NG |
| `ActivityLog` | activity_logs | jejak aktivitas |

### Pendukung
| File | Fungsi |
|------|--------|
| `app/Support/QcStatus.php` | Definisi & label status (RP/NG/SC/SR/Waiting), hitung total |
| `app/Traits/LogsActivity.php` | Helper mencatat aktivitas ke `activity_logs` |
| `app/Http/Middleware/EnsureUserRole.php` | Otorisasi per peran |
| `app/Http/Middleware/AddSecurityHeaders.php` | Header keamanan (CSP, X-Frame-Options, HSTS, dll) |
| `app/Exports/QcInspectionExcelExport.php` | Susun berkas Excel .xlsx |
| `app/Console/Commands/` | `qc:backup-database`, `machines:sync-accounts`, `qc:deployment-check`, + alat benchmark skripsi |

### Views (`resources/views/`)
- `auth/` login & ganti sandi · `admin/` master data · `qc/` input, hasil, laporan, dashboard
- `supervisor/` dashboard analitik · `mesin/` layar Akun Mesin
- `components/` komponen ulang-pakai (sidebar, tabel, badge status, modal, toast, kartu)
- `qc/exports/` & `qc/partials/` dokumen laporan (pratinjau, PDF, gaya)

---

## 5. "Kalau Mau Ubah X, Buka File Ini"

| Mau mengubah… | Buka |
|---------------|------|
| Identitas perusahaan, jam operasional, kata sandi/PIN default | `config/qc.php` (via `.env`) |
| Lama cache dashboard | `config/qc.php` → `dashboard_cache_ttl` |
| Daftar/aturan status QC | `app/Support/QcStatus.php` |
| Aturan validasi input QC | `QcInspectionController@validateWebInput` |
| Header keamanan / CSP | `app/Http/Middleware/AddSecurityHeaders.php` |
| Batas percobaan login | `AuthController` (`MAX_ATTEMPTS`, `DECAY_SECONDS`) |
| Batas percobaan PIN | `QcInspectionController` (`MAX_PIN_ATTEMPTS`, `PIN_DECAY_SECONDS`) |
| Filter/kolom laporan | `QcReportController` |
| Grafik supervisor (tren, bar, Pareto) | `resources/views/supervisor/dashboard.blade.php` (blok `@push('scripts')`) |
| Form input QC (tampilan & JS) | `resources/views/qc/input.blade.php` (JS ditandai SEKSI 1–9) |
| Menu sidebar per peran | `resources/views/components/sidebar.blade.php` |
| Rute baru | `routes/web.php` |

---

## 6. Merawat & Menjaga Kualitas

```bash
# Format kode (wajib sebelum commit) — sudah lolos saat ini
vendor/bin/pint

# Jalankan seluruh test
php artisan test          # atau: vendor/bin/phpunit

# Bersihkan cache konfigurasi setelah ubah .env / config
php artisan config:clear

# Backup database manual
php artisan qc:backup-database
```

**Prinsip menjaga agar tetap mudah dirawat:**
1. **Jangan ubah struktur tanpa alasan kuat.** Kode ini sudah rapi & ber-test.
2. **Setiap tambah fitur, tambah/perbarui test** di `tests/Feature`.
3. **Jalankan `pint` + `php artisan test` sebelum commit.** Keduanya harus hijau.
4. **Ubah nilai konfigurasi lewat `.env`**, bukan hard-code di kode.
5. **Setelah deploy**, jalankan `php artisan migrate --force` dan `npm run build`.
   Detail di `DEPLOYMENT.md`.

---

## 7. Catatan Keamanan (sudah diterapkan)

- Kata sandi & PIN **ter-hash** (bcrypt) — tak pernah disimpan polos.
- **Throttle** pada login dan verifikasi PIN (anti brute-force).
- **CSRF** aktif di semua form; input divalidasi; Blade auto-escape (anti XSS).
- **Content-Security-Policy** membatasi sumber aset ke domain sendiri.
- Otorisasi peran dicek ulang ke DB tiap request (menangkap akun dinonaktifkan).
- **Wajib saat go-live:** ganti semua kata sandi/PIN default di `config/qc.php`.
