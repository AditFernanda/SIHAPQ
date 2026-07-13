<x-layouts.app role="admin" title="Kelola Pengguna">
    @php
        $roleLabels = [
            'admin' => 'Admin',
            'supervisor' => 'Supervisor',
            'qc_inspector' => 'QC Inspector',
            'akun_mesin' => 'Akun Mesin',
        ];
    @endphp

    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-[18px] font-semibold">Kelola Data Pengguna</h1>
            <p class="text-[12px] text-text-secondary">Akun admin, supervisor, dan QC Inspector. Akun mesin dikelola dari Master Mesin.</p>
        </div>
        <a href="/admin/users?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah Pengguna</a>
    </div>
    
    
    <x-search-form action="/admin/users" :value="request('search')" placeholder="Cari nama pengguna, nama, atau peran..." reset-url="/admin/users" />
    
    <x-data-table data-search-results :headers="['No', 'Nama pengguna', 'Nama', 'Peran', 'Status', 'Aksi']">
        @forelse ($users as $user)
            <tr>
                <td class="whitespace-nowrap px-3 py-3">{{ $loop->iteration }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $user->username }}</td>
                <td class="whitespace-nowrap px-3 py-3">{{ $user->name }}</td>
                <td class="whitespace-nowrap px-3 py-3">
                    <span class="rounded-full bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary">
                        {{ $roleLabels[$user->role] ?? ucfirst(str_replace('_', ' ', $user->role)) }}
                    </span>
                </td>
                <td class="whitespace-nowrap px-3 py-3"><x-status-badge :status="$user->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                <td class="whitespace-nowrap px-3 py-3">
                    @if($user->role === 'akun_mesin')
                        <span class="text-[12px] text-text-secondary">Dikelola dari Master Mesin</span>
                    @else
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="/admin/users?edit={{ $user->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                                <i class="ti ti-pencil text-[13px]"></i>Ubah
                            </a>
                            @if((int) session('user_id') !== (int) $user->id)
                                <a href="/admin/users?reset={{ $user->id }}" class="inline-flex items-center gap-1 rounded-lg border border-border-tertiary bg-background-secondary px-2.5 py-1 text-[12px] font-semibold text-text-secondary hover:bg-white">
                                    <i class="ti ti-key text-[13px]"></i>Atur ulang
                                </a>
                            @endif
                            <form method="POST" action="/admin/users/{{ $user->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus pengguna {{ $user->username }}? Aksi ini tidak bisa dibatalkan.">
                                    <i class="ti ti-trash text-[13px]"></i>Hapus
                                </button>
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-3 py-6 text-center text-text-secondary">Tidak ada data pengguna</td>
            </tr>
        @endforelse
    </x-data-table>

    @if($showForm)
        <x-modal :title="$editingUser ? 'Ubah Pengguna' : 'Tambah Pengguna'" subtitle="Akun masuk aplikasi QC." close-url="/admin/users" max-width="760px">
            <form method="POST" action="{{ $editingUser ? '/admin/users/'.$editingUser->id : '/admin/users' }}">
                @csrf
                @if($editingUser)
                    @method('PUT')
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama pengguna</span><input name="username" value="{{ old('username', $editingUser->username ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus></label>
                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama lengkap</span><input name="name" value="{{ old('name', $editingUser->name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                    @unless($editingUser)
                        <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kata sandi awal</span><input type="password" name="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                        <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Konfirmasi kata sandi</span><input type="password" name="password_confirmation" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                    @endunless
                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Peran</span><select name="role" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required><option value="">Pilih Peran</option><option value="admin" @selected(old('role', $editingUser->role ?? '') === 'admin')>Admin</option><option value="supervisor" @selected(old('role', $editingUser->role ?? '') === 'supervisor')>Supervisor</option><option value="qc_inspector" @selected(old('role', $editingUser->role ?? '') === 'qc_inspector')>QC Inspector</option></select></label>
                    <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Status</span><select name="status" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary"><option value="aktif" @selected(old('status', $editingUser->status ?? 'aktif') === 'aktif')>Aktif</option><option value="nonaktif" @selected(old('status', $editingUser->status ?? 'aktif') === 'nonaktif')>Nonaktif</option></select></label>
                </div>
                <div class="mt-5 flex gap-2">
                    <a href="/admin/users" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a>
                    <button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingUser ? 'Perbarui' : 'Simpan' }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if($resettingUser)
        <x-modal title="Atur ulang kata sandi" :subtitle="'Atur ulang kata sandi untuk '.$resettingUser->name.' ('.$resettingUser->username.').'" close-url="/admin/users">
            <form method="POST" action="/admin/users/{{ $resettingUser->id }}/reset-password">
                @csrf
                @method('PUT')
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kata sandi baru</span><input name="password" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus></label>
                <label class="block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Konfirmasi kata sandi baru</span><input name="password_confirmation" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                <div class="mt-5 flex gap-2">
                    <a href="/admin/users" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a>
                    <button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">Atur ulang</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
