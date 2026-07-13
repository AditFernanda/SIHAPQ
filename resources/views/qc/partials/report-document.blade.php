{{--
    Badan dokumen laporan QC (dipakai untuk menghasilkan PDF via dompdf).

    Desain sengaja dibuat BERSIH & FORMAL (dominan hitam-putih, garis tipis abu),
    meniru form QC resmi di industri. Tidak memakai warna-warni agar mudah dibaca
    dan terlihat profesional saat dicetak. dompdf tidak mendukung flexbox/grid,
    sehingga seluruh tata letak memakai <table>.

    Variabel yang diharapkan:
      $printInspections, $filterSummary, $totalRows, $statusCounts, $generatedAt
--}}
@php
    $generatedAt = $generatedAt ?? now();
    $totalRows = $totalRows ?? $printInspections->count();
    // Urutan status untuk baris rekap ringkas.
    $recapOrder = ['RP', 'NG', 'SR', 'SC', 'WAITING'];
@endphp

<div class="report">
    {{-- Kop dokumen: brand kiri, judul tengah, kontrol dokumen kanan (format ISO). --}}
    <table class="report-head">
        <tr>
            <td class="head-brand">
                <div class="brand-name">{{ config('qc.company_name') }}</div>
                <div class="brand-sub">Sistem Informasi Hasil Pengecekan Quality</div>
            </td>
            <td class="head-title">
                <div class="doc-title">LAPORAN HASIL PENGECEKAN QUALITY</div>
                <div class="doc-subtitle">Quality Control Inspection Report</div>
            </td>
            <td class="head-doc">
                <table class="doc-control">
                    <tr><th>No. Dokumen</th><td>FM-QC-01</td></tr>
                    <tr><th>No. Revisi</th><td>00</td></tr>
                    <tr><th>Tgl. Cetak</th><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Baris informasi: ringkasan filter (kiri) & total baris (kanan). --}}
    <table class="report-info">
        <tr>
            <td class="info-filter">{{ $filterSummary ?: 'Periode: Semua tanggal' }}</td>
            <td class="info-total">Total Data: <strong>{{ number_format($totalRows) }}</strong> baris</td>
        </tr>
    </table>

    {{-- Rekap jumlah per status dalam satu baris teks ringkas (tanpa warna). --}}
    <table class="report-recap">
        <tr>
            <td class="recap-title">Rekap Status</td>
            @foreach ($recapOrder as $code)
                <td class="recap-item">{{ \App\Support\QcStatus::label($code) }}: <strong>{{ $statusCounts[$code] ?? 0 }}</strong></td>
            @endforeach
        </tr>
    </table>

    {{-- Tabel data utama. --}}
    <table class="data">
        <colgroup>
            <col style="width: 3%;">
            <col style="width: 8%;">
            <col style="width: 7%;">
            <col style="width: 6%;">
            <col style="width: 11%;">
            <col style="width: 8%;">
            <col style="width: 7%;">
            <col style="width: 8%;">
            <col style="width: 5%;">
            <col style="width: 5%;">
            <col style="width: 5%;">
            <col style="width: 9%;">
            <col style="width: 8%;">
            <col style="width: 13%;">
        </colgroup>
        <thead>
            <tr>
                @foreach (['No', 'Mesin', 'Bagian', 'Tanggal', 'Nama Part', 'No Part', 'Proses', 'Customer', 'Mulai', 'Selesai', 'Status', 'Jenis NG', 'PIC QC', 'Keterangan'] as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($printInspections as $inspection)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $inspection->machineName() }}</td>
                    <td>{{ $inspection->sectionName() }}</td>
                    <td class="c">{{ $inspection->inspection_date?->format('d/m/Y') }}</td>
                    <td>{{ $inspection->partName() }}</td>
                    <td>{{ $inspection->partNumber() }}</td>
                    <td>{{ $inspection->processName() }}</td>
                    <td>{{ $inspection->customerName() }}</td>
                    <td class="c">{{ $inspection->start_time ?: '-' }}</td>
                    <td class="c">{{ $inspection->end_time ?: '-' }}</td>
                    <td class="c status {{ $inspection->result_status === \App\Support\QcStatus::NG ? 'status-ng' : '' }}">{{ \App\Support\QcStatus::label($inspection->result_status) }}</td>
                    <td>{{ $inspection->ngTypeNames() }}</td>
                    <td>{{ $inspection->inspectorName() }}</td>
                    <td>{{ $inspection->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="empty">Tidak ada data sesuai filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Keterangan singkat arti kode status (pengganti legenda warna). --}}
    <div class="status-note">
        Keterangan: RP = Running Process &nbsp;·&nbsp; NG = No Good &nbsp;·&nbsp; SR = Special Request &nbsp;·&nbsp; SC = Special Control &nbsp;·&nbsp; Waiting = Menunggu Konfirmasi
    </div>

    {{-- Blok tanda tangan: Dibuat / Diperiksa / Disetujui. --}}
    <table class="signatures">
        <tr>
            <td>
                <div class="sign-role">Dibuat oleh,</div>
                <div class="sign-space"></div>
                <div class="sign-name">QC</div>
            </td>
            <td>
                <div class="sign-role">Diperiksa oleh,</div>
                <div class="sign-space"></div>
                <div class="sign-name">Supervisor QC</div>
            </td>
            <td>
                <div class="sign-role">Disetujui oleh,</div>
                <div class="sign-space"></div>
                <div class="sign-name">Manager</div>
            </td>
        </tr>
    </table>

    <div class="report-foot">
        Dokumen ini dihasilkan otomatis oleh sistem SIHAPQ. Tanggal cetak tertera pada kop dokumen.
    </div>
</div>
