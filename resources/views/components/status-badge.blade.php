@props(['status'])

@php
    $styles = [
        'Aktif' => 'bg-status-rp-bg text-status-rp-text',
        'Nonaktif' => 'bg-status-ng-bg text-status-ng-text',
    ];

    $badgeClass = $styles[$status] ?? \App\Support\QcStatus::badgeClass((string) $status);
    $displayStatus = array_key_exists((string) $status, $styles)
        ? $status
        : \App\Support\QcStatus::label((string) $status);
@endphp

<span class="{{ $badgeClass }} inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[12px] font-semibold">
    <span class="status-dot"></span>
    {{ $displayStatus }}
</span>
