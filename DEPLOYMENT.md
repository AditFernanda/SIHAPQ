# Panduan Deploy SIHAPQ PT RMI

Proyek ini adalah Laravel 12 + Blade + Vite + MySQL. Aplikasi bisa dipakai di hosting/intranet perusahaan atau di server lokal jaringan (LAN). Cara paling aman adalah document root diarahkan ke folder `public`.

## 1. Kebutuhan Hosting

- PHP 8.2 atau lebih baru
- Extension PHP umum Laravel: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
- Composer
- MySQL/MariaDB
- Node.js hanya diperlukan kalau ingin build asset di server. Kalau tidak, build dari lokal lalu upload folder `public/build`.

## 2. File Environment Production

Di server, buat `.env` dari `.env.production.example`, lalu sesuaikan:

```env
APP_NAME="SIHAPQ"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-kamu.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

QC_OPERATIONAL_DAY_START=07:00
QC_COMPANY_NAME="PT RMI"
QC_COMPANY_SHORT="RMI"
QC_MAIL_DOMAIN=pt-rmi.local
QC_ADMIN_DEFAULT_PASSWORD=ganti_password_admin_yang_kuat
QC_INSPECTOR_DEFAULT_PIN=654321
QC_MACHINE_DEFAULT_PASSWORD=ganti_password_mesin_yang_kuat
QC_BACKUP_KEEP_DAYS=14
QC_DASHBOARD_CACHE_TTL=60
```

Jangan pakai `DB_SOCKET` XAMPP di server hosting.

### Contoh `.env` untuk Server Lokal Jaringan Perusahaan

Gunakan ini bila aplikasi dipasang di satu PC/server kantor dan diakses dari komputer lain melalui jaringan lokal.

```env
APP_NAME="SIHAPQ"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.10

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sihapq
DB_USERNAME=sihapq_user
DB_PASSWORD=password_database_yang_kuat

SESSION_DRIVER=file
SESSION_ENCRYPT=false
CACHE_STORE=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

QC_OPERATIONAL_DAY_START=07:00
QC_COMPANY_NAME="PT RMI"
QC_COMPANY_SHORT="RMI"
QC_MAIL_DOMAIN=pt-rmi.local
QC_ADMIN_DEFAULT_PASSWORD=password_admin_awal_yang_kuat
QC_INSPECTOR_DEFAULT_PIN=654321
QC_MACHINE_DEFAULT_PASSWORD=password_mesin_awal_yang_kuat
QC_BACKUP_KEEP_DAYS=14
QC_DASHBOARD_CACHE_TTL=60
```

Catatan LAN:

- Ganti `192.168.1.10` dengan IP tetap server lokal.
- Beri IP statis/reservasi DHCP ke server agar alamat tidak berubah.
- Buka firewall server untuk HTTP/HTTPS sesuai web server yang dipakai.
- Komputer client cukup membuka `http://IP-SERVER` dari browser.
- Aplikasi tidak perlu internet untuk operasional harian setelah dependencies dan asset selesai dipasang.
- Halaman monitoring melakukan auto refresh ringan: akun mesin 30 detik, supervisor 60 detik.

## 3. Perintah Deploy

Jalankan dari root project di server:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan qc:deployment-check --strict
```

Untuk asset frontend:

```bash
npm install
npm run build
```

Jika server tidak menyediakan Node.js, jalankan `npm run build` di lokal lalu upload folder `public/build`.

Perintah bantu yang tersedia:

```bash
composer deploy:check
composer deploy:cache
composer deploy:clear
composer quality
```

- `composer deploy:check`: validasi konfigurasi production.
- `composer deploy:cache`: cache config, route, dan view setelah deploy.
- `composer deploy:clear`: bersihkan cache saat troubleshooting.
- `composer quality`: cek format kode, test backend, dan build asset.

## 4. Document Root

Document root hosting harus diarahkan ke:

```text
public/
```

Jangan arahkan domain ke root project Laravel, karena file seperti `.env`, `app`, `database`, dan `storage` tidak boleh bisa diakses publik.

### Khusus cPanel

Banyak akun cPanel tidak mengizinkan mengubah document root. Ada dua cara:

1. **Disarankan**: buat domain/subdomain dengan Document Root diarahkan ke `.../public`.
2. **Tanpa ubah document root**: unggah seluruh project ke dalam `public_html`.
   File `.htaccess` di root project sudah meneruskan permintaan ke `public/`,
   jadi aplikasi tetap jalan. Pastikan `public_html/.htaccess` (bawaan project)
   ikut terunggah.

Langkah ringkas di cPanel tanpa akses SSH:

- Build asset di lokal (`npm run build`), lalu unggah project beserta folder `vendor/` dan `public/build/`.
- Buat database dan user MySQL via menu **MySQL Databases**, lalu isi kredensialnya di `.env`.
- Jalankan migrasi lewat **Terminal** cPanel bila tersedia (`php artisan migrate --force`),
  atau impor file `.sql` hasil migrasi dari lokal lewat **phpMyAdmin**.
- Set permission folder `storage/` dan `bootstrap/cache/` agar dapat ditulis (755/775).

## 5. Akun Awal

Seeder hanya membuat akun admin awal. Data master seperti bagian, mesin, part, dan PIC QC dibuat dari menu admin agar data awal bersih.

- Username admin: `admin`
- Kata sandi admin: sesuai `QC_ADMIN_DEFAULT_PASSWORD`
- Kata sandi akun mesin: sesuai `QC_MACHINE_DEFAULT_PASSWORD`

Untuk production, jangan gunakan password default seperti `admin123`, `mesin123`, atau password mudah ditebak lain.

## 6. Keamanan Bawaan

Beberapa pengaman sudah aktif otomatis, tidak perlu konfigurasi tambahan:

- **Penguncian login**: setelah 5 percobaan gagal dari kombinasi nama pengguna + IP yang sama, login dikunci sementara selama 60 detik (mencegah brute-force). Atur di `app/Http/Controllers/AuthController.php` (`MAX_ATTEMPTS`, `DECAY_SECONDS`).
- **Kebijakan kata sandi**: setiap kata sandi baru wajib minimal 8 karakter serta mengandung huruf dan angka. Aturan terpusat di `app/Providers/AppServiceProvider.php` (`Password::defaults`).
- **Kata sandi baru harus berbeda** dari kata sandi lama saat ganti sandi.
- **Header keamanan** (X-Frame-Options, X-Content-Type-Options, dll.) dan **CSRF** aktif di semua form.

## 7. Checklist Setelah Deploy

- Buka `/login`
- Masuk sebagai admin
- Kelola master **Jenis NG** dari menu admin bila kategori bawaan belum sesuai proses pabrik
- Ganti semua kata sandi default (admin, akun mesin) dan PIN PIC QC
- Cek menu master data
- Cek input QC
- Cek dashboard akun mesin
- Untuk akun mesin, pastikan `/mesin/results` menampilkan semua mesin agar komputer bersama tetap bisa dipakai lintas area
- Cek auto refresh monitoring dengan input QC baru dari akun QC lalu lihat perubahan di akun mesin/supervisor
- Pastikan ekspor laporan berjalan
- Pastikan `APP_ENV=production` dan `APP_DEBUG=false`
- Pastikan document root mengarah ke `public/`
- Pastikan scheduler Laravel berjalan agar backup harian aktif
- Jalankan `php artisan qc:deployment-check --strict` dan pastikan hasilnya lolos
- Uji penguncian login: salah kata sandi 5x harus muncul pesan "Terlalu banyak percobaan masuk"
- Salin backup harian (`storage/app/backups`) ke lokasi lain secara berkala
