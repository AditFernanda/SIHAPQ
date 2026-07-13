@props([
    'title' => '',
    'subtitle' => null,
    'closeUrl' => '#',
    'maxWidth' => '460px',
])

{{--
    Modal overlay dengan header (judul + subtitle + tombol close).
    Body modal dipassing lewat slot $slot.

    Pemakaian:
        <x-modal title="Ubah Part" subtitle="Isi data part." close-url="/admin/products">
            <form ...>...</form>
        </x-modal>

    Optional props:
        - max-width: default 460px. Pakai 520px / 760px untuk form lebar.
--}}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
    <div class="max-h-[92vh] w-full overflow-y-auto rounded-xl border border-border-tertiary bg-white p-5 shadow-[0_24px_70px_rgb(44_44_42/0.22)]" style="max-width: {{ $maxWidth }}">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[16px] font-semibold">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-1 text-[12px] text-text-secondary">{{ $subtitle }}</p>
                @endif
            </div>
            <a href="{{ $closeUrl }}" class="flex size-8 items-center justify-center rounded-lg border border-border-tertiary text-text-secondary hover:text-text-primary" aria-label="Tutup">
                <i class="ti ti-x text-[16px]"></i>
            </a>
        </div>

        {{-- Error validasi ditampilkan di dalam modal agar tidak tertutup overlay. --}}
        @if ($errors->any())
            <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700">
                <i class="ti ti-alert-circle mt-0.5 text-[14px]"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
