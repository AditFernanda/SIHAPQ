{{--
    Komponen Auto-Refresh (mekanisme utama "near real-time" SIHAPQ).

    Latar belakang: layar Akun Mesin di area produksi harus menampilkan hasil QC
    terbaru tanpa operator menekan tombol apa pun. Karena aplikasi berjalan di
    shared hosting (cPanel) yang TIDAK mendukung koneksi persisten WebSocket,
    pembaruan dilakukan dengan cara sederhana: memuat ulang halaman secara
    berkala (polling). Ini disebut "near real-time" — bukan real-time murni,
    tetapi cukup untuk kebutuhan pemantauan QC yang tidak menuntut presisi detik.

    Parameter:
      $seconds — jeda refresh dalam detik (dipanggil dgn 30 di dashboard mesin).

    Pengaman: refresh DITUNDA bila halaman tidak sedang dilihat (tab lain) atau
    saat pengguna sedang mengetik di form, supaya tidak mengganggu input.
--}}
@props(['seconds' => 60])

<script>
    (() => {
        // Jeda antar-refresh (minimal 10 detik demi keamanan). $seconds x 1000 ms.
        const intervalMs = {{ max(10, (int) $seconds) }} * 1000;

        // Tentukan kapan refresh perlu ditunda agar tidak mengganggu pengguna.
        const shouldPause = () => {
            const active = document.activeElement;

            return document.visibilityState !== 'visible'        // tab tidak aktif
                || document.body.dataset.autoRefreshPaused === 'true' // sengaja dijeda
                || Boolean(active && active.closest('input, select, textarea')); // sedang mengisi form
        };

        // Timer berkala: muat ulang halaman bila tidak sedang perlu ditunda.
        window.setInterval(() => {
            if (!shouldPause()) {
                window.location.reload();
            }
        }, intervalMs);
    })();
</script>
