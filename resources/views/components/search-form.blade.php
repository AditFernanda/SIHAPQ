@props(['action', 'value' => '', 'placeholder' => 'Cari data...', 'label' => 'Pencarian', 'resetUrl' => null])

<form method="get" action="{{ $action }}" autocomplete="off"
    class="mb-4 rounded-xl border border-border-tertiary bg-white p-4">
    <div class="grid gap-3">
        <label class="block">
            <span
                class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">{{ $label }}</span>
            <input type="search" name="search" value="{{ $value }}"
                class="h-10 w-full rounded-lg border border-border-tertiary bg-white px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                placeholder="{{ $placeholder }}">
        </label>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <button
                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-[13px] font-semibold text-white sm:w-auto">
                <i class="ti ti-search text-[16px]"></i>
                Cari
            </button>
            @if ($resetUrl)
                <a href="{{ $resetUrl }}"
                    class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-border-tertiary bg-white px-4 text-center text-[13px] font-semibold text-text-primary sm:w-auto">Reset</a>
            @endif
        </div>
    </div>
</form>
