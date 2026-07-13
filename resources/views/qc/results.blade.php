@php
    $currentRole = $role ?? 'qc';
    $pageTitle = 'Hasil QC Semua Mesin';
    $pageSubtitle = 'Rekap hasil QC terakhir berdasarkan data mesin produksi.';
@endphp

<x-layouts.app :role="$currentRole" title="{{ $pageTitle }}">
    <div class="mb-4">
        <h1 class="text-[18px] font-semibold">{{ $pageTitle }}</h1>
        <p class="text-[12px] text-text-secondary">{{ $pageSubtitle }}</p>
    </div>
    <form method="get" autocomplete="off" class="mb-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[160px_160px_minmax(0,1fr)]">
            <label><span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Hari QC Dari</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"></label>
            <label><span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Hari QC Sampai</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"></label>
            <label class="md:col-span-2 xl:col-span-1"><span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Pencarian</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="h-10 w-full rounded-lg border border-border-tertiary bg-white px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Cari mesin, bagian, part, customer, jenis NG, atau keterangan..."></label>
        </div>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <span class="text-[12px] text-text-secondary">{{ $machineRows->count() }} dari {{ $machineRows->total() }} mesin</span>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white sm:w-auto"><i class="ti ti-search text-[16px]"></i>Cari</button>
                <a href="{{ url()->current() }}" class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-border-tertiary bg-white px-4 text-[13px] font-semibold text-text-primary sm:w-auto">Reset</a>
            </div>
        </div>
    </form>
    <x-data-table data-search-results :headers="['Mesin','Bagian','Tanggal','Nama Part','No Part','Proses','Customer','Jam Mulai','Jam Selesai','Status','Jenis NG','PIC QC','Keterangan']">
        @forelse ($machineRows as $machine)
            @php($inspection = $machine->latestQcInspection)
            <tr>
                <td class="whitespace-nowrap px-3 py-3">{{ $machine->displayName() }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $machine->department?->name ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->inspection_date?->translatedFormat('d M Y') ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->partName() ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->partNumber() ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->processName() ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->customerName() ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->start_time ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->end_time ?? '-' }}</td>
                <td class="px-3 py-3">
                    @if($inspection)
                        <x-status-badge :status="$inspection->result_status" />
                    @else
                        <span class="inline-flex rounded-full bg-background-secondary px-2 py-1 text-[11px] font-semibold text-text-secondary">Belum ada cek</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->ngTypeNames() ?? '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $inspection?->inspectorName() ?? '-' }}</td>
                <td class="px-3 py-3">{{ $inspection?->notes ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="px-3 py-6 text-center text-text-secondary">Tidak ada mesin sesuai filter.</td></tr>
        @endforelse
        <x-slot:pagination>{{ $machineRows->links() }}</x-slot:pagination>
    </x-data-table>

    @if ($currentRole === 'mesin')
        @push('scripts')
            <x-auto-refresh :seconds="30" />
        @endpush
    @endif
</x-layouts.app>
