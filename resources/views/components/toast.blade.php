{{--
    Toast notifikasi global: kartu kecil melayang di pojok kanan atas yang
    muncul sebentar lalu hilang sendiri (auto-dismiss ~4 detik).

    Membaca flash session Laravel yang SUDAH dipakai di seluruh controller:
      - session('success')  -> toast hijau
      - session('error')    -> toast merah
      - session('warning')  -> toast kuning

    Dipasang SEKALI di layout (components/layouts/app.blade.php), sehingga setiap
    halaman otomatis menampilkan toast tanpa perlu menulis apa pun di view.
--}}
@php
    $toasts = [];
    if (session('success')) {
        $toasts[] = ['type' => 'success', 'message' => session('success')];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'message' => session('error')];
    }
    if (session('warning')) {
        $toasts[] = ['type' => 'warning', 'message' => session('warning')];
    }

    // Error validasi (mis. PIN QC salah) ikut tampil sebagai toast merah.
    // Dikecualikan saat modal admin (create/edit/reset) terbuka karena modal
    // tersebut sudah menampilkan error di dalamnya sendiri -> hindari dobel.
    if ($errors->any() && ! session('error') && ! request()->hasAny(['create', 'edit', 'reset'])) {
        $toasts[] = ['type' => 'error', 'message' => $errors->first()];
    }

    $variants = [
        'success' => ['icon' => 'ti-circle-check', 'accent' => 'bg-green-500', 'iconColor' => 'text-green-600'],
        'error' => ['icon' => 'ti-alert-circle', 'accent' => 'bg-red-500', 'iconColor' => 'text-red-600'],
        'warning' => ['icon' => 'ti-alert-triangle', 'accent' => 'bg-amber-500', 'iconColor' => 'text-amber-600'],
    ];
@endphp

@if (! empty($toasts))
    <div class="toast-stack fixed right-4 top-4 z-100 flex w-[calc(100%-2rem)] max-w-xs flex-col gap-2" aria-live="polite" aria-atomic="true">
        @foreach ($toasts as $toast)
            @php $v = $variants[$toast['type']] ?? $variants['success']; @endphp
            <div class="toast-item relative flex items-start gap-3 overflow-hidden rounded-xl border border-border-tertiary bg-white px-4 py-3 shadow-[0_12px_32px_rgb(32_32_29/0.16)]" role="status" data-toast>
                <i class="ti {{ $v['icon'] }} mt-0.5 shrink-0 text-[18px] {{ $v['iconColor'] }}"></i>
                <p class="min-w-0 flex-1 text-[12px] font-medium leading-relaxed text-text-primary">{{ $toast['message'] }}</p>
                <button type="button" class="shrink-0 text-text-secondary transition hover:text-text-primary" data-toast-close aria-label="Tutup notifikasi">
                    <i class="ti ti-x text-[15px]"></i>
                </button>
                <span class="toast-progress absolute bottom-0 left-0 h-0.5 w-full {{ $v['accent'] }}"></span>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            (function () {
                const DURATION = 4000;
                document.querySelectorAll('[data-toast]').forEach(function (toast) {
                    const bar = toast.querySelector('.toast-progress');
                    let timer;

                    function dismiss() {
                        if (toast.dataset.dismissed) return;
                        toast.dataset.dismissed = '1';
                        clearTimeout(timer);
                        toast.classList.add('toast-out');
                        setTimeout(function () { toast.remove(); }, 260);
                    }

                    const closeBtn = toast.querySelector('[data-toast-close]');
                    if (closeBtn) closeBtn.addEventListener('click', dismiss);

                    if (bar) {
                        bar.style.setProperty('--toast-duration', DURATION + 'ms');
                        requestAnimationFrame(function () { bar.classList.add('toast-progress-run'); });
                    }

                    timer = setTimeout(dismiss, DURATION);
                });
            })();
        </script>
    @endpush
@endif
