<x-layouts.app :role="match(session('user_role')) { 'qc_inspector' => 'qc', 'akun_mesin' => 'mesin', 'supervisor' => 'supervisor', default => 'admin' }" title="Ganti kata sandi">
    <div class="mx-auto max-w-xl">
        <div class="mb-4">
            <h1 class="text-[18px] font-semibold">Ganti kata sandi</h1>
            <p class="text-[12px] text-text-secondary">Gunakan kata sandi yang mudah diingat dan tidak dibagikan ke pengguna lain.</p>
        </div>

        <form method="post" action="/password" class="rounded-xl border border-border-tertiary bg-white p-5">
            @csrf
            @method('put')
            <div class="mb-4 rounded-lg bg-background-secondary p-3 text-[12px]">
                <p class="font-semibold">{{ $user->name }}</p>
                <p class="text-text-secondary">{{ $user->username }} · {{ match($user->role) { 'admin' => 'Admin', 'supervisor' => 'Supervisor', 'qc_inspector' => 'QC Inspector', 'akun_mesin' => 'Akun Mesin', default => ucfirst(str_replace('_', ' ', $user->role)) } }}</p>
            </div>
            <label class="mb-3 block">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kata sandi lama</span>
                <div class="relative" data-password-field>
                    <input name="current_password" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 pr-10 outline-none focus:border-primary" required autofocus>
                    <button type="button" data-password-toggle aria-label="Tampilkan kata sandi" class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary hover:text-primary">
                        <i class="ti ti-eye text-[16px]"></i>
                    </button>
                </div>
            </label>
            <label class="mb-2 block">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Kata sandi baru</span>
                <div class="relative" data-password-field>
                    <input name="password" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 pr-10 outline-none focus:border-primary" required>
                    <button type="button" data-password-toggle aria-label="Tampilkan kata sandi" class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary hover:text-primary">
                        <i class="ti ti-eye text-[16px]"></i>
                    </button>
                </div>
            </label>
            <p class="mb-3 text-[12px] text-text-secondary">Minimal 8 karakter dan mengandung huruf serta angka.</p>
            <label class="block">
                <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Konfirmasi kata sandi baru</span>
                <div class="relative" data-password-field>
                    <input name="password_confirmation" type="password" class="w-full rounded-lg border border-border-tertiary px-3 py-2 pr-10 outline-none focus:border-primary" required>
                    <button type="button" data-password-toggle aria-label="Tampilkan kata sandi" class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary hover:text-primary">
                        <i class="ti ti-eye text-[16px]"></i>
                    </button>
                </div>
            </label>
            <div class="mt-5 flex justify-end">
                <button class="rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white">Simpan kata sandi</button>
            </div>
        </form>
    </div>
</x-layouts.app>
