<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'QC '.config('qc.company_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $sessionRole = session('user_role');
        $sidebarRole = match ($sessionRole) {
            'admin' => 'admin',
            'supervisor' => 'supervisor',
            'qc_inspector' => 'qc',
            'akun_mesin' => 'mesin',
            default => $role ?? 'admin',
        };
        $brandHome = match ($sidebarRole) {
            'admin' => '/admin/dashboard',
            'supervisor' => '/supervisor/dashboard',
            'qc' => '/qc/dashboard',
            'mesin' => '/mesin/dashboard',
            default => '/login',
        };
        $roleLabel = match ($sessionRole) {
            'admin' => 'Admin',
            'supervisor' => 'Supervisor',
            'qc_inspector' => 'QC Inspector',
            'akun_mesin' => 'Akun Mesin',
            default => 'Tamu',
        };
    @endphp
    <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    <div class="app-chrome min-h-screen text-text-primary lg:flex">
        <header class="mobile-topbar fixed top-0 right-0 left-0 z-40 flex items-center justify-between gap-3 border-b border-border-tertiary bg-white px-3 py-2 lg:hidden">
            <button type="button" data-sidebar-open class="flex size-10 items-center justify-center rounded-lg text-text-primary hover:bg-background-secondary" aria-label="Buka menu">
                <i class="ti ti-menu-2 text-[20px]"></i>
            </button>
            <a href="{{ $brandHome }}" class="flex min-w-0 items-center gap-2">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/12 text-primary ring-1 ring-primary/15">
                    <i class="ti ti-clipboard-check text-[16px]"></i>
                </span>
                <span class="truncate text-[13px] font-semibold">SIHAPQ</span>
            </a>
            <span class="inline-flex shrink-0 rounded-md bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary">{{ $roleLabel }}</span>
        </header>
        <div class="sidebar-backdrop fixed inset-0 z-40 hidden bg-black/35 lg:hidden" data-sidebar-backdrop aria-hidden="true"></div>
        <x-sidebar :role="$sidebarRole" />
        <main class="app-main min-w-0 flex-1 px-3 pt-17 pb-3 sm:px-4 sm:pb-4 lg:ml-50 lg:p-5">{{ $slot }}</main>
    </div>
    <x-toast />
    @stack('scripts')
</body>
</html>
