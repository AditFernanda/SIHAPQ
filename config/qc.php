<?php

return [
    // Identitas perusahaan. Ubah cukup di .env, berlaku ke seluruh tampilan & laporan.
    'company_name' => env('QC_COMPANY_NAME', 'PT RMI'),
    'company_short' => env('QC_COMPANY_SHORT', 'RMI'),
    'mail_domain' => env('QC_MAIL_DOMAIN', 'pt-rmi.local'),

    'operational_day_start' => env('QC_OPERATIONAL_DAY_START', '07:00'),
    'admin_default_password' => env('QC_ADMIN_DEFAULT_PASSWORD', 'admin123'),
    'qc_inspector_default_pin' => env('QC_INSPECTOR_DEFAULT_PIN', '123456'),
    'machine_default_password' => env('QC_MACHINE_DEFAULT_PASSWORD', 'mesin123'),
    'backup_path' => env('QC_BACKUP_PATH', storage_path('app/backups')),
    'backup_keep_days' => (int) env('QC_BACKUP_KEEP_DAYS', 14),

    // Lama (detik) hasil agregasi dashboard supervisor disimpan di cache.
    // Set 0 untuk menonaktifkan cache (berguna saat pengujian performa "tanpa cache").
    'dashboard_cache_ttl' => (int) env('QC_DASHBOARD_CACHE_TTL', 60),
];
