<x-layouts.app role="supervisor" title="Dashboard Supervisor">
    <div class="mb-4">
        <h1 class="text-[18px] font-semibold">Dashboard Supervisor</h1>
        <p class="text-[12px] text-text-secondary">Ringkasan kualitas, tren NG, dan prioritas masalah produksi.</p>
    </div>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
        <x-stat-card title="Total Hasil Cek QC Hari Ini" :value="$totalToday" icon="ti-clipboard-check" />
        <x-stat-card title="Running Process" :value="$todayStatusCounts['RP']" icon="ti-circle-check"
            tone="bg-status-rp-bg text-status-rp-text" />
        <x-stat-card title="Special Request" :value="$todayStatusCounts['SR']" icon="ti-message-2"
            tone="bg-status-sr-bg text-status-sr-text" />
        <x-stat-card title="Special Control" :value="$todayStatusCounts['SC']" icon="ti-eye-check"
            tone="bg-status-sc-bg text-status-sc-text" />
        <x-stat-card title="No Good" :value="$todayStatusCounts['NG']" icon="ti-alert-triangle"
            tone="bg-status-ng-bg text-status-ng-text" />
        <x-stat-card title="Waiting" :value="$todayStatusCounts['WAITING']" icon="ti-clock-pause" tone="bg-amber-100 text-amber-800" />
        <x-stat-card title="Persentase NG Hari Ini" value="{{ $ngRateToday }}%" icon="ti-trending-up"
            tone="bg-status-ng-bg text-status-ng-text" />
    </section>
    <section class="mt-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-[14px] font-semibold">Tren Persentase NG</h2>
                <p class="mt-1 text-[12px] text-text-secondary" data-trend-subtitle
                    data-text-daily="NG dihitung dari status NG dibanding total hasil cek QC per hari."
                    data-text-monthly="NG dihitung dari status NG dibanding total hasil cek QC per bulan kalender.">
                    NG dihitung dari status NG dibanding total hasil cek QC per hari.</p>
            </div>
            <div class="inline-flex w-fit rounded-lg border border-border-tertiary bg-background-secondary p-0.5 text-[12px]"
                role="tablist">
                <button type="button" data-trend-toggle="daily"
                    class="trend-toggle-btn rounded-md px-2.5 py-1 font-semibold" aria-pressed="true">30
                    Hari</button>
                <button type="button" data-trend-toggle="monthly"
                    class="trend-toggle-btn rounded-md px-2.5 py-1 font-semibold" aria-pressed="false">12
                    Bulan</button>
            </div>
        </div>
        <div class="mt-3 h-70"><canvas id="supervisorLine"></canvas></div>
    </section>
    <section class="mt-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-[14px] font-semibold">Analisis NG per Periode</h2>
                <p class="mt-1 text-[12px] text-text-secondary">Periode aktif: {{ $selectedNgPeriodLabel }}
                    ({{ $selectedNgPeriodRange }}) · Bagian: {{ $selectedDepartmentLabel }}.</p>
            </div>
            <form method="get" autocomplete="off"
                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[190px_190px_auto] sm:items-end">
                <label>
                    <span
                        class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Periode
                        NG</span>
                    <select name="periode_ng"
                        class="h-10 w-full rounded-lg border border-border-tertiary bg-white px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedNgPeriod === $value)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span
                        class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Bagian</span>
                    <select name="bagian_id"
                        class="h-10 w-full rounded-lg border border-border-tertiary bg-white px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="" @selected($selectedDepartmentId === null)>Semua bagian</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($selectedDepartmentId === $department->id)>
                                {{ $department->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white"><i
                        class="ti ti-filter text-[16px]"></i>Tampilkan</button>
            </form>
        </div>
        <div class="mt-4 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-[13px] font-semibold">Histogram Pareto Jenis NG per Periode</h3>
                        <p class="mt-1 text-[12px] text-text-secondary">Kategori seperti burry, dented, scratch, dan
                            jenis NG lain.</p>
                    </div>
                </div>
                <div class="mt-3 h-80"><canvas id="ngTypePareto"></canvas></div>
            </div>
            <div>
                <h3 class="text-[13px] font-semibold">Mesin dengan NG Terbanyak</h3>
                <p class="mt-1 text-[12px] text-text-secondary">Top 10 mesin pada periode aktif.</p>
                <div class="mt-3 h-80"><canvas id="machineBar"></canvas></div>
            </div>
        </div>
        <div class="mt-4">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h3 class="text-[13px] font-semibold">Top Part NG per Periode</h3>
                    <p class="mt-1 text-[12px] text-text-secondary">Part yang paling sering berstatus NG pada periode
                        aktif.</p>
                </div>
                <span class="text-[12px] font-semibold text-text-secondary">Maksimal 8 part teratas</span>
            </div>
            <x-data-table :headers="['Rank', 'Nama Part', 'No Part', 'Customer', 'Total NG', 'Jenis NG Dominan', 'Mesin Dominan']">
                @forelse ($topPartRows as $index => $part)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-3 font-semibold text-text-secondary">#{{ $index + 1 }}
                        </td>
                        <td class="px-3 py-3 font-semibold text-text-primary">{{ $part->part_name }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $part->part_number }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $part->customer_name }}</td>
                        <td class="whitespace-nowrap px-3 py-3 font-semibold text-status-ng-text">{{ $part->ng_count }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $part->dominant_ng_type }}</td>
                        <td class="whitespace-nowrap px-3 py-3">{{ $part->dominant_machine }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-text-secondary">Belum ada part NG pada
                            periode ini.</td>
                    </tr>
                @endforelse
            </x-data-table>
        </div>
    </section>

    <section class="mt-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="mb-3">
            <h2 class="text-[14px] font-semibold">NG Terbaru</h2>
            <p class="mt-1 text-[12px] text-text-secondary">Hasil NG terakhir yang perlu dipantau supervisor.</p>
        </div>
        <x-data-table class="shadow-none" :headers="['Mesin', 'Bagian', 'Tanggal', 'Part', 'Proses', 'Jenis NG', 'PIC QC', 'Keterangan']">
            @forelse ($latestAbInspections as $inspection)
                <tr>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->machineName() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->sectionName() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">
                        {{ $inspection->inspection_date?->translatedFormat('d M Y H:i') }}</td>
                    <td class="px-3 py-3">{{ $inspection->partName() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->processName() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->ngTypeNames() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->inspectorName() }}</td>
                    <td class="px-3 py-3 text-text-secondary">{{ $inspection->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-3 py-6 text-center text-text-secondary">Belum ada NG.</td>
                </tr>
            @endforelse
        </x-data-table>
    </section>
    @push('scripts')
        <script defer src="{{ asset('vendor/chartjs/chart.umd.js') }}?v=4.5.1"></script>
        <script defer>
            window.addEventListener('DOMContentLoaded', () => {
                // ── GRAFIK 1: Tren tingkat NG harian/bulanan (garis, bisa di-toggle) ──
                const trendDatasets = {
                    daily: {
                        labels: @json($trendLabels),
                        data: @json($trendData)
                    },
                    monthly: {
                        labels: @json($monthlyTrendLabels),
                        data: @json($monthlyTrendData)
                    },
                };
                const supervisorLineElement = document.getElementById('supervisorLine');
                const trendChart = supervisorLineElement && new Chart(supervisorLineElement, {
                    type: 'line',
                    data: {
                        labels: trendDatasets.daily.labels,
                        datasets: [{
                            label: 'NG %',
                            data: trendDatasets.daily.data,
                            borderColor: '#DC2626',
                            backgroundColor: 'rgba(180,35,24,.12)',
                            tension: .3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `NG: ${context.raw}%`
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: (value) => `${value}%`
                                }
                            }
                        }
                    }
                });

                const trendSubtitle = document.querySelector('[data-trend-subtitle]');
                const trendToggleBtns = document.querySelectorAll('[data-trend-toggle]');
                const applyTrendToggleStyle = (mode) => {
                    trendToggleBtns.forEach((btn) => {
                        const active = btn.dataset.trendToggle === mode;
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                        btn.classList.toggle('bg-white', active);
                        btn.classList.toggle('text-text-primary', active);
                        btn.classList.toggle('shadow-sm', active);
                        btn.classList.toggle('text-text-secondary', !active);
                    });
                };
                applyTrendToggleStyle('daily');
                trendToggleBtns.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const mode = btn.dataset.trendToggle;
                        const dataset = trendDatasets[mode];
                        if (!dataset || !trendChart) return;
                        trendChart.data.labels = dataset.labels;
                        trendChart.data.datasets[0].data = dataset.data;
                        trendChart.update();
                        if (trendSubtitle) {
                            trendSubtitle.textContent = mode === 'monthly' ?
                                trendSubtitle.dataset.textMonthly :
                                trendSubtitle.dataset.textDaily;
                        }
                        applyTrendToggleStyle(mode);
                    });
                });
                // ── GRAFIK 2: Mesin penyumbang NG terbanyak (bar horizontal) ─────────
                const machineBarElement = document.getElementById('machineBar');
                if (machineBarElement) new Chart(machineBarElement, {
                    type: 'bar',
                    data: {
                        labels: @json($machineLabels),
                        datasets: [{
                            label: 'NG',
                            data: @json($machineTotals),
                            backgroundColor: '#DC2626'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `NG: ${context.raw} data`
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                },
                                title: {
                                    display: true,
                                    text: 'Jumlah NG'
                                }
                            },
                            y: {
                                ticks: {
                                    autoSkip: false
                                }
                            }
                        }
                    }
                });
                // ── GRAFIK 3: Diagram Pareto jenis NG (bar jumlah + garis kumulatif %) ─
                const ngTypeParetoElement = document.getElementById('ngTypePareto');
                if (ngTypeParetoElement) {
                    new Chart(ngTypeParetoElement, {
                        data: {
                            labels: @json($ngTypeParetoLabels),
                            datasets: [{
                                type: 'bar',
                                label: 'Jumlah NG',
                                data: @json($ngTypeParetoTotals),
                                backgroundColor: '#DC2626',
                                borderRadius: 4,
                                yAxisID: 'y'
                            }, {
                                type: 'line',
                                label: 'Kumulatif',
                                data: @json($ngTypeParetoCumulative),
                                borderColor: '#1570EF',
                                backgroundColor: '#1570EF',
                                tension: .25,
                                pointRadius: 3,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 10,
                                        usePointStyle: true
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (context) => context.dataset.type === 'line' ?
                                            `Kumulatif: ${context.raw}%` : `NG: ${context.raw} data`
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 0
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Jumlah NG'
                                    }
                                },
                                y1: {
                                    beginAtZero: true,
                                    max: 100,
                                    position: 'right',
                                    grid: {
                                        drawOnChartArea: false
                                    },
                                    ticks: {
                                        callback: (value) => `${value}%`
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
        <x-auto-refresh :seconds="60" />
    @endpush
</x-layouts.app>
