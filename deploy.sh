#!/usr/bin/env bash
# Deploy SIHAPQ di server cPanel/hosting dengan akses SSH.
# Jalankan dari root project setelah kode terbaru sudah tersedia di branch main.
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f ".env" ]; then
    echo "File .env belum ada. Buat dari .env.production.example lalu isi konfigurasi server."
    exit 1
fi

echo "==> 1/6 Menarik kode terbaru dari GitHub"
git pull origin main

echo "==> 2/6 Install dependency PHP production"
composer install --no-dev --optimize-autoloader

echo "==> 3/6 Migrasi database"
php artisan migrate --force

echo "==> 4/6 Membersihkan cache lama"
php artisan optimize:clear
if [ -f "public/hot" ]; then
    rm "public/hot"
fi

echo "==> 5/6 Membangun cache production"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> 6/6 Cek konfigurasi production"
php artisan qc:deployment-check --strict

echo ""
echo "DEPLOY SELESAI. Buka domain lalu tekan Ctrl+Shift+R."
