<x-layouts.app role="admin" title="Master Jenis NG">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[18px] font-semibold">Kelola Jenis NG</h1>
            <p class="text-[12px] text-text-secondary">Jenis NG dipilih saat input QC berstatus NG dan dipakai untuk Histogram Pareto.</p>
        </div>
        <a href="/admin/ng-types?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah Jenis NG</a>
    </div>


    <x-search-form action="/admin/ng-types" :value="$filters['search'] ?? ''" placeholder="Cari jenis NG..." reset-url="/admin/ng-types" />

    <x-data-table data-search-results :headers="['Jenis NG', 'Jumlah Riwayat', 'Status', 'Aksi']">
        @forelse ($ngTypes as $ngType)
            <tr>
                <td class="px-3 py-3 font-semibold">{{ $ngType->name }}</td>
                <td class="px-3 py-3">{{ $ngType->qc_inspections_count }}</td>
                <td class="px-3 py-3"><x-status-badge :status="$ngType->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                <td class="px-3 py-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="/admin/ng-types?edit={{ $ngType->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                            <i class="ti ti-pencil text-[13px]"></i>Ubah
                        </a>
                        <form method="POST" action="/admin/ng-types/{{ $ngType->id }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus jenis NG {{ $ngType->name }}? Jika sudah dipakai, data akan dinonaktifkan.">
                                <i class="ti ti-trash text-[13px]"></i>Hapus
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-3 py-6 text-center text-text-secondary">Belum ada jenis NG.</td></tr>
        @endforelse
    </x-data-table>

    @if($showForm)
        <x-modal :title="$editingNgType ? 'Ubah Jenis NG' : 'Tambah Jenis NG'" subtitle="Nama jenis NG akan tampil di input QC dan Histogram Pareto supervisor." close-url="/admin/ng-types">
            <form method="POST" action="{{ $editingNgType ? '/admin/ng-types/'.$editingNgType->id : '/admin/ng-types' }}">
                @csrf
                @if($editingNgType) @method('PUT') @endif
                <label class="mb-3 block">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama Jenis NG</span>
                    <input name="name" value="{{ old('name', $editingNgType->name ?? '') }}" maxlength="80" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus>
                </label>
                <label class="mb-4 block">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Status</span>
                    <select name="status" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required>
                        <option value="aktif" @selected(old('status', $editingNgType->status ?? 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $editingNgType->status ?? 'aktif') === 'nonaktif')>Nonaktif</option>
                    </select>
                </label>
                <div class="flex gap-2">
                    <a href="/admin/ng-types" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a>
                    <button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingNgType ? 'Perbarui' : 'Simpan' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
