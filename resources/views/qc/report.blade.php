<x-layouts.app :role="$role ?? 'qc'" title="Laporan QC">
    @php
        // Satu pintu ekspor: buka halaman pratinjau, lalu pilih Unduh PDF/Excel/Cetak di sana.
        $exportUrl =
            url(($role ?? 'qc') === 'supervisor' ? '/supervisor/report/pdf' : '/qc/report/pdf') .
            '?' .
            http_build_query(array_filter($filters, fn($value) => filled($value)));
        $activeFilters = collect($filters)->filter(fn($value) => filled($value))->count();
    @endphp

    <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
            <h1 class="text-[20px] font-semibold">Laporan QC</h1>
            <p class="mt-0.5 text-[12px] text-text-secondary">Rekap hasil pengecekan QC berdasarkan periode.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $exportUrl }}" target="_blank" rel="noopener"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white">
                <i class="ti ti-file-export text-[16px]"></i>
                Cetak / Ekspor
            </a>
        </div>
    </div>

    <form method="get" autocomplete="off" class="rounded-xl border border-border-tertiary bg-white p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[160px_160px_minmax(0,1fr)]">
            <label>
                <span
                    class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
            </label>
            <label>
                <span
                    class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Sampai</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
            </label>
            <label class="md:col-span-2 xl:col-span-1">
                <span
                    class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Pencarian</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Mesin, bagian, part, customer, jenis NG, keterangan..."
                    class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
            </label>
        </div>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <button
                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white sm:w-auto"><i
                    class="ti ti-search text-[16px]"></i>
                Cari
            </button>
            <a href="{{ url(($role ?? 'qc') === 'supervisor' ? '/supervisor/report' : '/qc/report') }}"
                class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-border-tertiary bg-white px-4 text-center text-[13px] font-semibold text-text-primary sm:w-auto">Reset</a>
        </div>
    </form>

    <section data-search-results class="mt-3 overflow-hidden rounded-xl border border-border-tertiary bg-white">
        <div
            class="flex flex-col gap-1 border-b border-border-tertiary px-4 py-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-[14px] font-semibold">Data Laporan</h2>
                <p class="text-[12px] text-text-secondary">Menampilkan {{ $inspections->count() }} dari
                    {{ $inspections->total() }} data.</p>
            </div>
            @if ($activeFilters > 0)
                <span
                    class="w-fit rounded-lg bg-background-secondary px-3 py-1 text-[12px] font-medium text-text-secondary">{{ $activeFilters }}
                    filter aktif</span>
            @endif
        </div>
        <div class="p-4">
            <x-data-table :headers="[
                'Mesin',
                'Bagian',
                'Tanggal',
                'Nama Part',
                'No Part',
                'Proses',
                'Customer',
                'Jam Mulai',
                'Jam Selesai',
                'Status',
                'Jenis NG',
                'PIC QC',
                'Keterangan',
            ]">
                @forelse ($inspections as $inspection)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->machineName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->sectionName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">
                            {{ $inspection->inspection_date?->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->partName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->partNumber() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->processName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->customerName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->start_time ?: '-' }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->end_time ?: '-' }}</td>
                        <td class="px-3 py-3"><x-status-badge :status="$inspection->result_status" /></td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->ngTypeNames() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->inspectorName() }}</td>
                        <td class="px-3 py-3">{{ $inspection->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-3 py-6 text-center text-text-secondary">Tidak ada data sesuai
                            filter.</td>
                    </tr>
                @endforelse
                <x-slot:pagination>{{ $inspections->links() }}</x-slot:pagination>
            </x-data-table>
        </div>
    </section>

</x-layouts.app>
