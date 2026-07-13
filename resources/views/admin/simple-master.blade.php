<x-layouts.app role="admin" :title="'Master '.$type">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div><h1 class="text-[18px] font-semibold">Kelola {{ $type }}</h1><p class="text-[12px] text-text-secondary">Bagian dipakai untuk mengelompokkan mesin dan filter laporan QC.</p></div>
        <a href="/admin/departments?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah {{ $type }}</a>
    </div>


    <x-search-form action="/admin/departments" :value="$filters['search'] ?? ''" placeholder="Cari nama bagian atau deskripsi..." reset-url="/admin/departments" />

    <div class="grid gap-4">
        <x-data-table data-search-results :headers="['Nama', 'Deskripsi', 'Jumlah Mesin', 'Status', 'Aksi']">
            @forelse ($items as $item)
                <tr>
                    <td class="px-3 py-3">{{ $item->name }}</td>
                    <td class="px-3 py-3">{{ $item->description ?? '-' }}</td>
                    <td class="px-3 py-3">{{ $item->machines_count ?? 0 }}</td>
                    <td class="px-3 py-3"><x-status-badge :status="$item->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="/admin/departments?edit={{ $item->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                                <i class="ti ti-pencil text-[13px]"></i>Ubah
                            </a>
                            <form method="POST" action="/admin/departments/{{ $item->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus bagian {{ $item->name }}? Bagian yang masih dipakai mesin tidak bisa dihapus.">
                                    <i class="ti ti-trash text-[13px]"></i>Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-text-secondary">Belum ada data {{ strtolower($type) }}.</td></tr>
            @endforelse
        </x-data-table>
    </div>

    @if($showForm)
        <x-modal :title="$editingItem ? 'Ubah '.$type : 'Tambah '.$type" subtitle="Bagian dipakai oleh data mesin." close-url="/admin/departments">
            <form method="POST" action="{{ $editingItem ? '/admin/departments/'.$editingItem->id : '/admin/departments' }}">
                @csrf
                @if($editingItem) @method('PUT') @endif
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama</span><input name="name" value="{{ old('name', $editingItem->name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus></label>
                <label class="mb-4 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Deskripsi</span><textarea name="description" rows="3" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary">{{ old('description', $editingItem->description ?? '') }}</textarea></label>
                <div class="flex gap-2"><a href="/admin/departments" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a><button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingItem ? 'Perbarui' : 'Simpan' }}</button></div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
