<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - SIHAPQ {{ config('qc.company_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <main class="app-chrome flex min-h-screen flex-col items-center justify-center p-4">
        <section class="surface-card w-full max-w-95 rounded-xl p-6">
            <div class="mb-5 text-center">
                <img src="{{ asset('favicon.svg') }}" alt="Logo" class="mx-auto mb-3 size-12 rounded-xl">
                <p class="mb-1 font-display text-[15px] font-bold uppercase tracking-[0.12em] text-primary">SIHAPQ</p>
                <h1 class="text-[15px] font-semibold">Sistem Informasi Hasil Pengecekan Quality</h1>
                <p class="mt-0.5 text-[12px] text-text-secondary">Akses internal {{ config('qc.company_name') }}</p>
            </div>

            @if ($errors->any())
                <div
                    class="mb-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700">
                    <i class="ti ti-alert-circle mt-0.5 text-[14px]"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="/login" method="post" class="space-y-3">
                @csrf
                <label class="block">
                    <span
                        class="mb-1 block text-[12px] font-medium uppercase tracking-[0.06em] text-text-secondary">Nama
                        pengguna</span>
                    <input name="username" value="{{ old('username') }}"
                        class="w-full rounded-lg border border-border-tertiary px-3 py-2.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                        placeholder="Contoh: admin atau kode mesin" required autofocus>
                </label>
                <label class="block">
                    <span
                        class="mb-1 block text-[12px] font-medium uppercase tracking-[0.06em] text-text-secondary">Kata
                        sandi</span>
                    <div class="relative" data-password-field>
                        <input name="password" type="password"
                            class="w-full rounded-lg border border-border-tertiary px-3 py-2.5 pr-10 outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                            placeholder="Masukkan kata sandi" required>
                        <button type="button" data-password-toggle aria-label="Tampilkan kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary hover:text-primary">
                            <i class="ti ti-eye text-[16px]"></i>
                        </button>
                    </div>
                </label>
                <button
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white transition hover:bg-primary-deep">
                    <i class="ti ti-login-2 text-[16px]"></i>
                    Masuk
                </button>
            </form>

            <div
                class="mt-4 rounded-lg border border-border-tertiary bg-background-secondary px-3 py-2 text-center text-[12px] text-text-secondary">
                Lupa kata sandi? Hubungi Admin sistem.
            </div>
        </section>
        <p class="mt-5 text-[12px] text-text-secondary">© {{ date('Y') }} {{ config('qc.company_name') }}. Seluruh
            hak cipta dilindungi.</p>
    </main>
</body>

</html>
