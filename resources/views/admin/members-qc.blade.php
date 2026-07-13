<x-layouts.app role="admin" title="PIC QC">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[18px] font-semibold">Kelola PIC QC</h1>
            <p class="text-[12px] text-text-secondary">Data PIC QC untuk verifikasi PIN saat input hasil QC.</p>
        </div>
        @unless($showForm)
            <a href="/admin/members-qc?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah PIC QC</a>
        @endunless
    </div>
    <x-search-form action="/admin/members-qc" :value="request('search')" placeholder="Cari NIK atau nama PIC QC..." reset-url="/admin/members-qc" />
    <div class="grid gap-4">
        <x-data-table data-search-results :headers="['NIK', 'Nama', 'PIN', 'Status', 'Aksi']">
            @forelse ($inspectors as $inspector)
                <tr>
                    <td class="px-3 py-3">{{ $inspector->employee_id }}</td>
                    <td class="px-3 py-3">{{ $inspector->name }}</td>
                    <td class="px-3 py-3"><span class="inline-flex rounded border border-border-tertiary px-2 py-1 font-mono text-[12px] text-text-secondary">••••••</span></td>
                    <td class="px-3 py-3"><x-status-badge :status="$inspector->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="/admin/members-qc?edit={{ $inspector->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                                <i class="ti ti-pencil text-[13px]"></i>Ubah
                            </a>
                            <form action="/admin/members-qc/{{ $inspector->id }}" method="post" class="inline">
                                @csrf @method('delete')
                                <button class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus anggota QC {{ $inspector->name }} ({{ $inspector->employee_id }})?">
                                    <i class="ti ti-trash text-[13px]"></i>Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-text-secondary">Belum ada PIC QC.</td></tr>
            @endforelse
        </x-data-table>
    </div>

    @if($showForm)
        <x-modal :title="$editingInspector ? 'Ubah PIC QC' : 'Tambah PIC QC'" subtitle="PIN PIC QC hanya untuk verifikasi input hasil QC, bukan kata sandi login aplikasi." close-url="/admin/members-qc">
            <form action="{{ $editingInspector ? '/admin/members-qc/'.$editingInspector->id : '/admin/members-qc' }}" method="post">
                @csrf
                @if($editingInspector)
                    @method('put')
                @endif
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">NIK</span><input name="employee_id" value="{{ old('employee_id', $editingInspector->employee_id ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus></label>
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama</span><input name="name" value="{{ old('name', $editingInspector->name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                <label class="mb-3 block">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">PIN 6 digit</span>
                    <input name="pin" value="{{ old('pin') }}" maxlength="6" inputmode="numeric" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" {{ $editingInspector ? '' : 'required' }}>
                    <span class="mt-1 block text-[12px] text-text-secondary">PIN ini dipakai saat PIC QC menyimpan hasil pengecekan.</span>
                    @if($editingInspector)
                        <span class="mt-1 block text-[12px] text-text-secondary">Kosongkan jika PIN tidak diubah.</span>
                    @endif
                </label>
                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Status</span><select name="status" class="w-full rounded-lg border border-border-tertiary px-3 py-2"><option value="aktif" @selected(old('status', $editingInspector->status ?? 'aktif') === 'aktif')>Aktif</option><option value="nonaktif" @selected(old('status', $editingInspector->status ?? 'aktif') === 'nonaktif')>Nonaktif</option></select></label>
                <div class="mt-5 flex gap-2">
                    <a href="/admin/members-qc" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a>
                    <button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingInspector ? 'Perbarui' : 'Simpan' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
