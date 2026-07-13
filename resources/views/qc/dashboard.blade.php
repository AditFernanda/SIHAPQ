<x-layouts.app role="qc" title="Dashboard Quality Control">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[18px] font-semibold">Dashboard Quality Control</h1>
            <p class="text-[12px] text-text-secondary">Monitoring input hasil pengecekan dan waiting QC.</p>
        </div>
        <a href="/qc/input" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white">
            <i class="ti ti-clipboard-check text-[16px]"></i>Input Hasil QC
        </a>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <x-stat-card title="Total Hasil Cek QC Hari Ini" :value="$totalToday" icon="ti-clipboard-check" />
        <x-stat-card title="Running Process" :value="$statusCounts['RP']" icon="ti-circle-check" tone="bg-status-rp-bg text-status-rp-text" />
        <x-stat-card title="No Good" :value="$statusCounts['NG']" icon="ti-alert-triangle" tone="bg-status-ng-bg text-status-ng-text" />
        <x-stat-card title="Special Request" :value="$statusCounts['SR']" icon="ti-message-2" tone="bg-status-sr-bg text-status-sr-text" />
        <x-stat-card title="Special Control" :value="$statusCounts['SC']" icon="ti-eye-check" tone="bg-status-sc-bg text-status-sc-text" />
        <x-stat-card title="Waiting" :value="$statusCounts['WAITING']" icon="ti-clock-pause" tone="bg-amber-100 text-amber-800" />
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-xl border border-border-tertiary bg-white p-4">
            <div class="mb-3">
                <h2 class="text-[14px] font-semibold">Waiting Belum Selesai</h2>
                <p class="text-[12px] text-text-secondary">Input yang masih menunggu keputusan final.</p>
            </div>

            <div class="divide-y divide-border-tertiary">
                @forelse ($pendingInspections as $inspection)
                    <div class="grid gap-3 py-3 md:grid-cols-[1fr_auto] md:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold">{{ $inspection->machineName() }} · {{ $inspection->partName() }}</p>
                            <p class="mt-0.5 truncate font-mono text-[11px] text-text-secondary">{{ $inspection->partNumber() }} · {{ $inspection->processName() }} · {{ $inspection->start_time ?: '-' }}</p>
                            @if($inspection->notes)
                                <p class="mt-1 line-clamp-2 text-[12px] text-text-secondary">{{ $inspection->notes }}</p>
                            @endif
                        </div>
                        <a href="/qc/input?resolve={{ $inspection->id }}" class="inline-flex h-9 items-center justify-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 text-[12px] font-semibold text-amber-800 hover:bg-amber-100">
                            <i class="ti ti-checkup-list text-[14px]"></i>Selesaikan
                        </a>
                    </div>
                @empty
                    <div class="py-6 text-center text-[12px] text-text-secondary">Tidak ada waiting QC.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-border-tertiary bg-white p-4">
            <div class="mb-3">
                <h2 class="text-[14px] font-semibold">Input Terakhir Hari QC Ini</h2>
                <p class="text-[12px] text-text-secondary">Data terbaru pada periode {{ \App\Models\QcInspection::operationalDayStart() }} sampai {{ \App\Models\QcInspection::operationalDayStart() }}.</p>
            </div>

            <div class="divide-y divide-border-tertiary">
                @forelse ($recentInspections as $inspection)
                    <div class="grid gap-3 py-3 md:grid-cols-[86px_1fr_92px_118px] md:items-center">
                        <div class="text-[12px] font-semibold text-text-primary">{{ $inspection->start_time ?: '-' }}</div>
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-semibold">{{ $inspection->machineName() }} · {{ $inspection->partName() }}</p>
                            <p class="mt-0.5 truncate font-mono text-[11px] text-text-secondary">{{ $inspection->partNumber() }} · {{ $inspection->processName() }}{{ $inspection->customerName() ? ' · '.$inspection->customerName() : '' }}</p>
                        </div>
                        <div><x-status-badge :status="$inspection->result_status" /></div>
                        <div class="truncate text-[12px] text-text-secondary">{{ $inspection->inspectorName() }}</div>
                    </div>
                @empty
                    <div class="py-6 text-center text-[12px] text-text-secondary">Belum ada input QC pada hari QC ini.</div>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
