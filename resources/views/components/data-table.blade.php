@props(['headers' => []])

<div {{ $attributes->merge(['class' => 'table-shell overflow-hidden rounded-lg border border-border-tertiary bg-white shadow-[0_6px_18px_rgb(32_32_29/0.04)]']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border-tertiary text-left text-[12px]" data-responsive-table data-headers='@json($headers, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
            @if (count($headers))
                <thead class="border-b border-[#DDE5EE] bg-[#EEF2F7] text-[12px] font-semibold uppercase tracking-[0.06em] text-[#5F6B76]">
                    <tr>
                        @foreach ($headers as $header)
                            <th class="whitespace-nowrap px-3 py-3">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-border-tertiary">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($pagination)
        <div class="border-t border-border-tertiary px-3 py-3">
            {{ $pagination }}
        </div>
    @endisset
</div>
