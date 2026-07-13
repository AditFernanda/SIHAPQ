<x-layouts.app role="admin" title="Master Mesin">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div><h1 class="text-[18px] font-semibold">Kelola Master Mesin</h1><p class="text-[12px] text-text-secondary">Data mesin produksi per bagian.</p></div>
        <a href="/admin/machines?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah Mesin</a>
    </div>


    <x-search-form action="/admin/machines" :value="request('search')" placeholder="Cari kode mesin, nama mesin, atau bagian..." reset-url="/admin/machines" />

    <div class="grid gap-4">
        <x-data-table data-search-results :headers="['Kode', 'Nama Mesin', 'Bagian', 'Status', 'Akun Mesin', 'Aksi']">
            @forelse ($machines as $machine)
                @php $machineUser = $machine->users->first(); @endphp
                <tr>
                    <td class="px-3 py-3">{{ $machine->machine_code }}</td>
                    <td class="px-3 py-3">{{ $machine->name }}</td>
                    <td class="px-3 py-3">{{ $machine->department?->name ?? '-' }}</td>
                    <td class="px-3 py-3"><x-status-badge :status="$machine->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                    <td class="px-3 py-3">
                        @if($machineUser)
                            <span class="font-mono text-xs text-text-primary">{{ $machineUser->username }}</span>
                        @else
                            <span class="text-xs text-text-secondary italic">Belum ada</span>
                        @endif
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="/admin/machines?edit={{ $machine->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                                <i class="ti ti-pencil text-[13px]"></i>Ubah
                            </a>
                            @if($machineUser)
                                <a href="/admin/machines?reset={{ $machine->id }}" class="inline-flex items-center gap-1 rounded-lg border border-border-tertiary bg-background-secondary px-2.5 py-1 text-[12px] font-semibold text-text-secondary hover:bg-white">
                                    <i class="ti ti-key text-[13px]"></i>Kata sandi
                                </a>
                            @endif
                            <form method="POST" action="/admin/machines/{{ $machine->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus mesin {{ $machine->machine_code }}? Akun mesin terkait juga akan dihapus.">
                                    <i class="ti ti-trash text-[13px]"></i>Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-text-secondary">Tidak ada data mesin</td></tr>
            @endforelse
        </x-data-table>

    </div>

    @if($showForm)
        <x-modal :title="$editingMachine ? 'Ubah Mesin' : 'Tambah Mesin'" subtitle="Kode mesin menjadi nama pengguna akun mesin. Setelah akun dibuat, ubah kata sandi mesin jika perlu." close-url="/admin/machines" max-width="520px">
            <form method="POST" action="{{ $editingMachine ? '/admin/machines/'.$editingMachine->id : '/admin/machines' }}">
                @csrf
                @if($editingMachine) @method('PUT') @endif
                <div class="grid gap-3">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kode Mesin</span>
                        <input name="machine_code" value="{{ old('machine_code', $editingMachine->machine_code ?? '') }}" placeholder="Contoh: 60T-4" class="w-full rounded-lg border border-border-tertiary px-3 py-2 font-mono outline-none focus:border-primary" required autofocus>
                        <span class="mt-1 block text-[12px] text-text-secondary">Kode ini dipakai sebagai nama pengguna akun mesin.</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama Mesin</span>
                        <input name="name" value="{{ old('name', $editingMachine->name ?? '') }}" placeholder="Contoh: WASHINO" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required>
                        <span class="mt-1 block text-[12px] text-text-secondary">Nama mesin wajib diisi untuk identitas tampilan dan akun mesin.</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Bagian</span><select name="department_id" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required><option value="">Pilih Bagian</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $editingMachine->department_id ?? '') == $department->id)>{{ $department->name }}</option>@endforeach</select></label>
                        <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Status</span><select name="status" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary"><option value="aktif" @selected(old('status', $editingMachine->status ?? 'aktif') === 'aktif')>Aktif</option><option value="nonaktif" @selected(old('status', $editingMachine->status ?? 'aktif') === 'nonaktif')>Nonaktif</option></select></label>
                    </div>
                </div>
                <div class="mt-5 flex gap-2"><a href="/admin/machines" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a><button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingMachine ? 'Perbarui' : 'Simpan' }}</button></div>
            </form>
        </x-modal>
    @endif

    @if($resettingMachine)
        @php($resettingMachineUser = $resettingMachine->users->first())
        <x-modal title="Ubah kata sandi mesin" :subtitle="'Akun mesin '.$resettingMachine->machine_code.($resettingMachineUser ? ' ('.$resettingMachineUser->username.')' : '').'.'" close-url="/admin/machines">
            @if($resettingMachineUser)
                <form method="POST" action="/admin/machines/{{ $resettingMachine->id }}/reset-password">
                    @csrf
                    @method('PUT')
                    <label class="mb-3 block">
                        <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kata sandi baru</span>
                        <input name="password" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Konfirmasi kata sandi baru</span>
                        <input name="password_confirmation" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required>
                    </label>
                    <div class="mt-5 flex gap-2">
                        <a href="/admin/machines" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a>
                        <button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">Simpan kata sandi</button>
                    </div>
                </form>
            @else
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">Akun mesin belum tersedia. Simpan ulang data mesin untuk membuat akun otomatis.</div>
                <div class="mt-5">
                    <a href="/admin/machines" class="block rounded-lg border border-border-tertiary px-4 py-2 text-center">Tutup</a>
                </div>
            @endif
        </x-modal>
    @endif
</x-layouts.app>
