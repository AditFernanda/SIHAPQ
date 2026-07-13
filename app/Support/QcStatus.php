<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Sumber tunggal definisi status QC: kode tersimpan, label tampilan,
 * deskripsi, warna badge, dan aturan lolos/akhir.
 */
final class QcStatus
{
    // --- Daftar kode status hasil QC (arti lengkap ada di method description()) ---
    public const RP = 'RP';           // Running Process  — proses berjalan normal (lolos)

    public const NG = 'NG';           // No Good          — produk cacat/tidak lolos

    public const SR = 'SR';           // Special Request  — dilepas atas permintaan khusus (lolos)

    public const SC = 'SC';           // Special Control  — dilepas dengan kontrol khusus (lolos)

    public const WAITING = 'WAITING'; // Menunggu Konfirmasi — belum jadi keputusan final

    // Semua status yang mungkin dipilih di form input QC.
    public const ALL = [
        self::RP,
        self::NG,
        self::SR,
        self::SC,
        self::WAITING,
    ];

    // Status "final" = sudah jadi keputusan. WAITING sengaja TIDAK termasuk karena
    // masih menunggu tindak lanjut, sehingga tidak dihitung dalam rekap harian.
    public const FINAL_STATUSES = [
        self::RP,
        self::NG,
        self::SR,
        self::SC,
    ];

    // Status yang dianggap LOLOS QC. NG (cacat) dikecualikan; SR & SC tetap
    // dihitung lolos sesuai aturan internal perusahaan.
    public const PASS_STATUSES = [
        self::RP,
        self::SR,
        self::SC,
    ];

    public static function validationRule(): string
    {
        return 'required|in:'.implode(',', self::ALL);
    }

    public static function isPassing(string $status): bool
    {
        return in_array($status, self::PASS_STATUSES, true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::NG => 'NG',
            self::WAITING => 'WAITING',
            default => $status,
        };
    }

    public static function description(string $status): string
    {
        return match ($status) {
            self::RP => 'Running Process',
            self::NG => 'No Good',
            self::SR => 'Special Request',
            self::SC => 'Special Control',
            self::WAITING => 'Menunggu Konfirmasi',
            default => $status,
        };
    }

    public static function emptyCounts(): array
    {
        return array_fill_keys(self::ALL, 0);
    }

    public static function countsFrom(Collection $counts): array
    {
        return collect(self::emptyCounts())
            ->map(fn ($total, $status) => (int) ($counts[$status] ?? 0))
            ->all();
    }

    public static function finalTotal(array $counts): int
    {
        return collect(self::FINAL_STATUSES)->sum(fn ($status) => (int) ($counts[$status] ?? 0));
    }

    public static function passTotal(array $counts): int
    {
        return collect(self::PASS_STATUSES)->sum(fn ($status) => (int) ($counts[$status] ?? 0));
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::RP => 'bg-status-rp-bg text-status-rp-text',
            self::NG => 'bg-status-ng-bg text-status-ng-text',
            self::SR => 'bg-status-sr-bg text-status-sr-text',
            self::SC => 'bg-status-sc-bg text-status-sc-text',
            self::WAITING => 'bg-amber-100 text-amber-800',
            default => 'bg-black/5 text-text-secondary',
        };
    }
}
