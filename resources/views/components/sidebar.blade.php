@props(['role' => 'admin'])

@php
    $roleLabels = [
        'admin' => 'Admin',
        'qc' => 'QC Inspector',
        'supervisor' => 'Supervisor',
        'mesin' => 'Akun Mesin',
    ];

    $menus = [
        'admin' => [
            ['label' => 'Dashboard', 'icon' => 'ti-layout-dashboard', 'url' => '/admin/dashboard'],
            ['label' => 'Data Pengguna', 'icon' => 'ti-users', 'url' => '/admin/users'],
            ['label' => 'PIC QC', 'icon' => 'ti-id-badge-2', 'url' => '/admin/members-qc'],
            ['label' => 'Master Part', 'icon' => 'ti-package', 'url' => '/admin/products'],
            ['label' => 'Jenis NG', 'icon' => 'ti-alert-triangle', 'url' => '/admin/ng-types'],
            ['label' => 'Master Mesin', 'icon' => 'ti-settings-automation', 'url' => '/admin/machines'],
            ['label' => 'Bagian', 'icon' => 'ti-building-factory-2', 'url' => '/admin/departments'],
            ['label' => 'Riwayat Aktivitas', 'icon' => 'ti-history', 'url' => '/admin/activity-logs'],
        ],
        'qc' => [
            ['label' => 'Dashboard', 'icon' => 'ti-layout-dashboard', 'url' => '/qc/dashboard'],
            ['label' => 'Input Hasil QC', 'icon' => 'ti-clipboard-check', 'url' => '/qc/input'],
            ['label' => 'Hasil QC Semua Mesin', 'icon' => 'ti-table', 'url' => '/qc/results'],
            ['label' => 'Laporan QC', 'icon' => 'ti-file-export', 'url' => '/qc/report'],
        ],
        'supervisor' => [
            ['label' => 'Dashboard', 'icon' => 'ti-layout-dashboard', 'url' => '/supervisor/dashboard'],
            ['label' => 'Hasil QC Semua Mesin', 'icon' => 'ti-table', 'url' => '/supervisor/results'],
            ['label' => 'Laporan QC', 'icon' => 'ti-file-analytics', 'url' => '/supervisor/report'],
        ],
        'mesin' => [
            ['label' => 'Status Mesin', 'icon' => 'ti-activity', 'url' => '/mesin/dashboard'],
            ['label' => 'Hasil QC Semua Mesin', 'icon' => 'ti-table', 'url' => '/mesin/results'],
        ],
    ];

    $homeUrl = $menus[$role][0]['url'] ?? '/login';
    $currentMachine = null;
    if ($role === 'mesin') {
        $userId = session('user_id');
        if ($userId) {
            $currentUser = \App\Models\User::with(['machines.department'])->find($userId);
            $currentMachine = $currentUser?->machines->first();
        }
    }

    $displayName = session('user_name', 'Pengguna');
    $displayRoleLabel = $roleLabels[$role] ?? 'Pengguna';
    $normalizedDisplayName = str($displayName)->lower()->squish()->toString();
    $showRoleLabel = ! in_array($normalizedDisplayName, ['admin', 'administrator', str($displayRoleLabel)->lower()->squish()->toString()], true);
@endphp

<aside id="appSidebar" class="app-sidebar fixed inset-y-0 left-0 z-50 w-65 border-r border-border-tertiary bg-white shadow-[0_16px_42px_rgb(32_32_29/0.12)] lg:w-50 lg:shadow-[0_4px_18px_rgb(32_32_29/0.04)]" data-sidebar aria-hidden="true">
    <div class="sidebar-shell flex h-full flex-col px-3 py-4">
        <div class="sidebar-header mb-5 border-b border-border-tertiary pb-3">
        <div class="flex items-center gap-2">
            <a href="{{ $homeUrl }}" class="sidebar-brand flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-2 py-2 text-text-primary">
                <span class="sidebar-logo flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-white shadow-[0_8px_16px_rgb(33_150_243/0.18)]">
                    <i class="ti ti-clipboard-check text-[19px]"></i>
                </span>
                <span class="sidebar-text sidebar-brand-copy min-w-0">
                    <span class="block font-display text-[18px] font-bold leading-none tracking-[0.08em] text-text-primary">SIHAPQ</span>
                    <span class="mt-1 block w-29 whitespace-normal text-[9.5px] font-medium leading-[1.2] tracking-[0.02em] text-text-secondary">Sistem Informasi Hasil Pengecekan Quality</span>
                </span>
            </a>
            <button type="button" data-sidebar-close class="flex size-9 shrink-0 items-center justify-center rounded-lg text-text-secondary hover:bg-background-secondary lg:hidden" aria-label="Tutup menu">
                <i class="ti ti-x text-[18px]"></i>
            </button>
        </div>
        <div class="sidebar-toggle-row flex justify-center">
            <button type="button" data-sidebar-toggle class="relative hidden h-8 w-11 items-center justify-center rounded-lg bg-transparent text-text-secondary transition hover:bg-white hover:text-primary hover:shadow-[0_8px_18px_rgb(44_44_42/0.05)] lg:flex" title="Geser sidebar" aria-label="Geser sidebar">
                <i class="ti ti-layout-sidebar-left-collapse sidebar-collapse-icon absolute text-[17px]"></i>
                <i class="ti ti-layout-sidebar-left-expand sidebar-expand-icon absolute text-[17px]"></i>
            </button>
        </div>
        </div>

        <nav class="sidebar-nav flex flex-1 flex-col gap-1">
            @foreach ($menus[$role] ?? [] as $menu)
                @php $active = request()->is(ltrim($menu['url'], '/') . '*'); @endphp
                <a href="{{ $menu['url'] }}" title="{{ $menu['label'] }}" class="{{ $active ? 'bg-[#EAF5FE] text-primary' : 'text-text-secondary hover:bg-[#F4F7FA] hover:text-text-primary' }} group relative flex items-center gap-2 rounded-md px-3 py-2 text-[12px] font-medium transition">
                    @if ($active)
                        <span class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-r-full bg-primary"></span>
                    @endif
                    <i class="ti {{ $menu['icon'] }} shrink-0 text-[16px]"></i>
                    <span class="sidebar-text sidebar-menu-label">{{ $menu['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @if ($role === 'mesin')
            {{-- Identitas ringkas (kode mesin). Detail lengkap (bagian & status)
                 tampil di header halaman, tidak diulang di sini. Gaya kartu sama
                 persis dengan profil peran lain agar konsisten & rapi. --}}
            <div class="sidebar-profile mb-3 rounded-lg border border-border-tertiary bg-[#F7F9FB] p-3">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-background-secondary text-primary"><i class="ti ti-settings-automation text-[17px]"></i></span>
                    <span class="sidebar-text min-w-0">
                        <p class="truncate text-[12px] font-semibold">{{ $currentMachine?->machine_code ?? '-' }}</p>
                        <p class="mt-0.5 inline-flex rounded-md bg-primary/10 px-1.5 py-0.5 text-[11px] font-semibold text-primary">Akun Mesin</p>
                    </span>
                </div>
            </div>
        @else
            <div class="sidebar-profile mb-3 rounded-lg border border-border-tertiary bg-[#F7F9FB] p-3">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-background-secondary text-primary"><i class="ti ti-user-circle text-[17px]"></i></span>
                    <span class="sidebar-text min-w-0">
                        <p class="truncate text-[12px] font-semibold">{{ $displayName }}</p>
                        @if($showRoleLabel)
                            <p class="mt-0.5 inline-flex rounded-md bg-primary/10 px-1.5 py-0.5 text-[11px] font-semibold text-primary">{{ $displayRoleLabel }}</p>
                        @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="sidebar-actions shrink-0">
            @if(($role ?? null) !== 'mesin')
                <a href="/password" title="Ganti kata sandi" class="mb-2 flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-[12px] font-medium text-text-secondary transition hover:bg-[#F4F7FA] hover:text-text-primary">
                    <i class="ti ti-key shrink-0 text-[16px]"></i>
                    <span class="sidebar-text">Ganti kata sandi</span>
                </a>
            @endif

            <form action="/logout" method="post">
                @csrf
                <button title="Keluar" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-[12px] font-medium text-text-secondary transition hover:bg-[#F4F7FA] hover:text-text-primary">
                <i class="ti ti-logout shrink-0 text-[16px]"></i>
                <span class="sidebar-text">Keluar</span>
                </button>
            </form>
        </div>
    </div>
</aside>
