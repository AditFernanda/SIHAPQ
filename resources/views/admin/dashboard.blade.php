<x-layouts.app role="admin" title="Dashboard Admin">
    @php
        $roleLabels = [
            'admin' => 'Admin',
            'supervisor' => 'Supervisor',
            'qc_inspector' => 'QC Inspector',
            'akun_mesin' => 'Akun Mesin',
        ];
    @endphp

    <div class="mb-4">
        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-primary">Administrasi</p>
        <h1 class="mt-1 text-[18px] font-semibold text-text-primary">Dashboard Admin</h1>
        <p class="mt-1 text-[12px] text-text-secondary">Ringkasan pengguna, master data, dan aktivitas terakhir.</p>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-stat-card title="Total Pengguna" :value="$totalUsers" icon="ti-users"
            :description="$totalDepartments . ' bagian terdaftar'" description-icon="ti-building-factory-2" />
        <x-stat-card title="Total Mesin" :value="$machineStats['total']" icon="ti-settings-automation"
            :description="$machineStats['active'] . ' aktif'" description-icon="ti-circle-check" />
        <x-stat-card title="Total Part" :value="$productStats['total']" icon="ti-package"
            :description="$productStats['active'] . ' aktif'" description-icon="ti-circle-check" />
        <x-stat-card title="PIC QC" :value="$inspectorStats['total']" icon="ti-id-badge-2"
            :description="$inspectorStats['active'] . ' aktif'" description-icon="ti-circle-check" />
        <x-stat-card title="Jenis NG" :value="$ngTypeStats['total']" icon="ti-alert-triangle"
            :description="$ngTypeStats['active'] . ' aktif'" description-icon="ti-circle-check" />
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-border-tertiary bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-[14px] font-semibold">Pengguna per Peran</h2>
                <a href="/admin/users" class="text-[12px] font-medium text-primary">Kelola pengguna</a>
            </div>
            <div class="space-y-4">
                @foreach ($usersByRole as $role => $count)
                    @php $percent = $totalUsers > 0 ? min(round(($count / $totalUsers) * 100), 100) : 0; @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-[12px]">
                            <span class="font-medium">{{ $roleLabels[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}</span>
                            <span class="text-text-secondary">{{ $count }} akun</span>
                        </div>
                        <div class="h-2 rounded-full bg-background-secondary">
                            <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-border-tertiary bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-[14px] font-semibold">Mesin per Bagian</h2>
                <a href="/admin/departments" class="text-[12px] font-medium text-primary">Kelola bagian</a>
            </div>
            <div class="space-y-4">
                @forelse ($departmentMachines as $department)
                    @php $percent = $totalMachines > 0 ? min(round(($department->machines_count / $totalMachines) * 100), 100) : 0; @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-[12px]">
                            <span class="font-medium">{{ $department->name }}</span>
                            <span class="text-text-secondary">{{ $department->machines_count }} mesin</span>
                        </div>
                        <div class="h-2 rounded-full bg-background-secondary">
                            <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-[12px] text-text-secondary">Belum ada bagian.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-border-tertiary bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-[14px] font-semibold">Part per Customer</h2>
                <a href="/admin/products" class="text-[12px] font-medium text-primary">Kelola part</a>
            </div>
            <div class="space-y-3">
                @forelse($customerProductRows as $row)
                    @php $percent = $productStats['total'] > 0 ? min(round(($row->total / $productStats['total']) * 100), 100) : 0; @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-[12px]">
                            <span class="font-medium">{{ $row->customer_name ?: 'Belum Ada Customer' }}</span>
                            <span class="text-text-secondary">{{ $row->total }} part</span>
                        </div>
                        <div class="h-2 rounded-full bg-background-secondary">
                            <div class="h-2 rounded-full bg-primary" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-[12px] text-text-secondary">Belum ada part.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mt-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="text-[14px] font-semibold">Aktivitas Terakhir</h2>
                <p class="text-[12px] text-text-secondary">Perubahan terbaru pada akun, master data, dan input hasil QC.</p>
            </div>
            <a href="/admin/activity-logs" class="text-[12px] font-medium text-primary">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-[12px]">
                <thead class="text-[11px] uppercase tracking-[0.08em] text-text-secondary">
                    <tr>
                        <th class="border-b border-border-tertiary px-3 py-2">Waktu</th>
                        <th class="border-b border-border-tertiary px-3 py-2">Aktivitas</th>
                        <th class="border-b border-border-tertiary px-3 py-2">Pengguna</th>
                        <th class="border-b border-border-tertiary px-3 py-2">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentAdminActivities as $activity)
                        <tr>
                            <td class="whitespace-nowrap border-b border-border-tertiary px-3 py-2">{{ $activity->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap border-b border-border-tertiary px-3 py-2 font-medium">{{ $activity->activity }}</td>
                            <td class="whitespace-nowrap border-b border-border-tertiary px-3 py-2">{{ $activity->user?->name ?? '-' }}</td>
                            <td class="border-b border-border-tertiary px-3 py-2 text-text-secondary">{{ $activity->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-text-secondary">Belum ada aktivitas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
