<x-layouts.app role="mesin" title="Dashboard Mesin">
    @php
        $resultMeta = [
            'RP' => [
                'label' => 'Running Process',
                'action' => 'BOLEH PRODUKSI',
                'instruction' => 'Produksi boleh berjalan sesuai part yang dicek.',
                'bg' => 'bg-status-rp-bg',
                'text' => 'text-status-rp-text',
                'icon' => 'ti-circle-check',
                'border' => 'border-status-rp-text/30',
            ],
            'NG' => [
                'label' => 'No Good',
                'action' => 'TAHAN PRODUKSI',
                'instruction' => 'Produksi ditahan. Hubungi atasan sebelum melanjutkan.',
                'bg' => 'bg-status-ng-bg',
                'text' => 'text-status-ng-text',
                'icon' => 'ti-alert-triangle',
                'border' => 'border-status-ng-text/50',
            ],
            'SR' => [
                'label' => 'Special Request',
                'action' => 'BOLEH PRODUKSI DENGAN REQUEST',
                'instruction' => 'Produksi boleh berjalan dengan mengikuti request/catatan QC.',
                'bg' => 'bg-status-sr-bg',
                'text' => 'text-status-sr-text',
                'icon' => 'ti-message-2',
                'border' => 'border-status-sr-text/30',
            ],
            'SC' => [
                'label' => 'Special Control',
                'action' => 'BOLEH PRODUKSI DENGAN CONTROL',
                'instruction' => 'Produksi boleh berjalan dengan control khusus sesuai catatan QC.',
                'bg' => 'bg-status-sc-bg',
                'text' => 'text-status-sc-text',
                'icon' => 'ti-eye-check',
                'border' => 'border-status-sc-text/30',
            ],
            'WAITING' => [
                'label' => 'Menunggu Konfirmasi',
                'action' => 'TUNGGU KEPUTUSAN QC',
                'instruction' => 'Produksi ditahan sementara sampai QC menyimpan keputusan final.',
                'bg' => 'bg-amber-50',
                'text' => 'text-amber-800',
                'icon' => 'ti-clock-pause',
                'border' => 'border-amber-300',
            ],
        ];
        $current = $latestInspection ? $resultMeta[$latestInspection->result_status] ?? null : null;
        $isFresh = $latestInspection?->inspection_date && $latestInspection->inspection_date->gt(now()->subHours(2));
    @endphp

    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-[18px] font-semibold">Mesin {{ $machine?->machine_code ?? '-' }}</h1>
                @if ($machine)
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-status-rp-bg px-2 py-0.5 text-[11px] font-semibold text-status-rp-text">
                        <span class="size-1.5 rounded-full bg-status-rp-text animate-pulse"></span>
                        Aktif
                    </span>
                @endif
            </div>
            <p class="text-[12px] text-text-secondary">{{ $machine?->department?->name ?? 'Belum terdaftar' }} · Hasil QC
                terbaru untuk mesin ini.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="rounded-lg border border-border-tertiary bg-white px-3 py-2 text-[12px] text-text-secondary">{{ now()->translatedFormat('l, d F Y H:i') }}</span>
        </div>
    </div>

    @if (!$machine)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
            <p class="flex items-center gap-2 text-[13px] font-semibold"><i class="ti ti-alert-circle"></i> Mesin belum
                di-link ke akun ini</p>
            <p class="mt-1 text-[12px]">Hubungi admin untuk menautkan akun ini dengan data mesin.</p>
        </div>
    @else
        @if ($latestInspection && $current)
            <section
                class="rounded-2xl border {{ $current['border'] }} {{ $current['bg'] }} p-4 sm:p-5 {{ $latestInspection->result_status === 'NG' ? 'ring-2 ring-status-ng-text/20' : '' }}">
                <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex size-12 items-center justify-center rounded-xl bg-white/70 {{ $current['text'] }} shadow-sm">
                            <i class="ti {{ $current['icon'] }} text-[24px]"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-[0.08em] {{ $current['text'] }}">Hasil
                                QC Terbaru</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded bg-white/70 px-2 py-0.5 text-[14px] font-semibold {{ $current['text'] }}">{{ \App\Support\QcStatus::label($latestInspection->result_status) }}</span>
                                <h2
                                    class="text-[18px] font-semibold leading-tight sm:text-[20px] {{ $current['text'] }}">
                                    {{ $current['label'] }}</h2>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                        @if ($isFresh)
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-semibold text-status-ng-text shadow-sm">
                                BARU · {{ $latestInspection->inspection_date->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                    <div class="min-w-0">
                        <div
                            class="inline-flex max-w-full rounded-lg bg-white/80 px-3 py-1 text-[12px] font-bold leading-snug {{ $current['text'] }}">
                            {{ $current['action'] }}</div>
                        <p class="mt-2 text-[13px] font-semibold leading-relaxed text-text-primary">
                            {{ $current['instruction'] }}</p>
                        <p class="mt-2 text-[12px] leading-relaxed text-text-primary">
                            Part <strong>{{ $latestInspection->partName() }}</strong>
                            ({{ $latestInspection->partNumber() }})
                            proses <strong>{{ $latestInspection->processName() }}</strong>
                            untuk <strong>{{ $latestInspection->customerName() }}</strong>
                        </p>
                        @if ($latestInspection->notes)
                            <div class="mt-3 rounded-xl bg-white/80 px-4 py-3 text-text-primary">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-text-secondary">
                                    Catatan QC</p>
                                <p class="mt-1 text-[14px] font-semibold leading-relaxed">
                                    {{ $latestInspection->notes }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl bg-white/70 p-3 text-[12px] text-text-primary">
                        <p class="text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Detail
                            Pemeriksaan</p>
                        <dl class="mt-2 grid gap-1.5">
                            <div class="flex justify-between gap-2">
                                <dt class="text-text-secondary">PIC QC</dt>
                                <dd class="font-medium">{{ $latestInspection->inspectorName() }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-text-secondary">Jenis NG</dt>
                                <dd class="font-medium text-right">
                                    {{ $latestInspection->result_status === \App\Support\QcStatus::NG ? $latestInspection->ngTypeNames() : '-' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-text-secondary">Tanggal</dt>
                                <dd class="font-medium">
                                    {{ $latestInspection->inspection_date->translatedFormat('d M Y') }}</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-text-secondary">Jam Cek</dt>
                                <dd class="font-medium">{{ $latestInspection->start_time }} –
                                    {{ $latestInspection->end_time }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-dashed border-border-tertiary bg-white p-8 text-center">
                <i class="ti ti-inbox text-[36px] text-text-secondary"></i>
                <p class="mt-3 text-[14px] font-semibold">Belum ada hasil QC</p>
                <p class="mt-1 text-[12px] text-text-secondary">Hasil pengecekan untuk mesin ini akan muncul di sini
                    segera setelah QC menyimpan input.</p>
            </section>
        @endif

        <section class="mt-4 rounded-xl border border-border-tertiary bg-white p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-[14px] font-semibold">Ringkasan Status Mesin Hari Ini</h2>
                    <p class="text-[12px] text-text-secondary">Periode QC:
                        {{ config('qc.operational_day_start', '07:00') }} -
                        {{ config('qc.operational_day_start', '07:00') }}. Instruksi produksi mengikuti hasil QC
                        terbaru di atas.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-[12px]">
                    @foreach ([['RP', 'bg-status-rp-bg text-status-rp-text'], ['SR', 'bg-status-sr-bg text-status-sr-text'], ['SC', 'bg-status-sc-bg text-status-sc-text'], ['NG', 'bg-status-ng-bg text-status-ng-text'], ['WAITING', 'bg-amber-100 text-amber-800']] as [$code, $tone])
                        <span
                            class="{{ $tone }} rounded-lg px-3 py-1.5 font-semibold">{{ \App\Support\QcStatus::label($code) }}:
                            {{ $statusCounts[$code] }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mt-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-[14px] font-semibold">Riwayat Pengecekan Mesin {{ $machine?->machine_code }}</h2>
                <span class="text-[12px] text-text-secondary">{{ count($history) }} input terbaru</span>
            </div>
            <x-data-table :headers="[
                'Tanggal',
                'Jam',
                'Nama Part',
                'No Part',
                'Proses',
                'Customer',
                'Status',
                'Jenis NG',
                'PIC QC',
                'Keterangan',
            ]">
                @forelse ($history as $inspection)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-3">
                            {{ $inspection->inspection_date?->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-text-secondary">{{ $inspection->start_time }} –
                            {{ $inspection->end_time }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->partName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3 font-mono text-[12px]">{{ $inspection->partNumber() }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->processName() }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->customerName() }}</td>
                        <td class="px-3 py-3"><x-status-badge :status="$inspection->result_status" /></td>
                        <td class="whitespace-nowrap px-3 py-3">
                            {{ $inspection->result_status === \App\Support\QcStatus::NG ? $inspection->ngTypeNames() : '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $inspection->inspectorName() }}</td>
                        <td class="px-3 py-3 text-text-secondary">{{ $inspection->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-3 py-6 text-center text-text-secondary">Belum ada riwayat untuk
                            mesin ini.</td>
                    </tr>
                @endforelse
            </x-data-table>
        </section>
    @endif

    @push('scripts')
        <x-auto-refresh :seconds="30" />
    @endpush
</x-layouts.app>
