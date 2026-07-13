<x-layouts.app role="admin" title="Riwayat Aktivitas">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[18px] font-semibold">Riwayat Aktivitas</h1>
            <p class="text-[12px] text-text-secondary">Riwayat perubahan akun, master data, dan input hasil QC.</p>
        </div>
        <span
            class="rounded-lg border border-border-tertiary bg-white px-3 py-2 text-[12px] text-text-secondary">{{ $logs->total() }}
            aktivitas</span>
    </div>

    <form method="get" autocomplete="off" class="mb-4 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="grid gap-3 lg:grid-cols-[240px_minmax(0,1fr)]">
            <label>
                <span
                    class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Kategori</span>
                <select name="category"
                    class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <option value="">Semua kategori</option>
                    @foreach ($categoryOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span
                    class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Pencarian</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                    placeholder="Cari pengguna, aktivitas, atau deskripsi...">
            </label>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center lg:col-span-2 sm:justify-end">
                <button
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white sm:w-auto"><i
                        class="ti ti-search text-[16px]"></i>Cari</button>
                <a href="/admin/activity-logs"
                    class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-border-tertiary bg-white px-4 text-center text-[13px] font-semibold text-text-primary sm:w-auto">Reset</a>
            </div>
        </div>
    </form>

    <x-data-table data-search-results :headers="['Waktu', 'Pengguna', 'Aktivitas', 'Deskripsi', 'IP']">
        @forelse ($logs as $log)
            <tr>
                <td class="whitespace-nowrap px-3 py-3">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                <td class="whitespace-nowrap px-3 py-3">
                    <p class="font-semibold">{{ $log->user?->name ?? '-' }}</p>
                    <p class="text-[11px] text-text-secondary">{{ $log->user?->username ?? '-' }}</p>
                </td>
                <td class="whitespace-nowrap px-3 py-3">{{ $log->activity }}</td>
                <td class="min-w-80 px-3 py-3 text-text-secondary">{{ $log->description ?: '-' }}</td>
                <td class="whitespace-nowrap px-3 py-3 text-text-secondary">{{ $log->ip_address ?: '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-3 py-6 text-center text-text-secondary">Belum ada aktivitas.</td>
            </tr>
        @endforelse
        <x-slot:pagination>{{ $logs->links() }}</x-slot:pagination>
    </x-data-table>
</x-layouts.app>
