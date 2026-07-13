@props(['title', 'value', 'icon' => 'ti-chart-bar', 'tone' => '', 'description' => null, 'descriptionIcon' => 'ti-trending-up'])

<div class="surface-card metric-card rounded-lg p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[12px] font-medium uppercase tracking-[0.06em] text-text-secondary">{{ $title }}</p>
            <p class="mt-2 text-[22px] font-semibold leading-none text-text-primary">{{ $value }}</p>
            @if($description)
                <p class="mt-2 flex items-center gap-1 text-[12px] font-medium text-text-secondary">
                    <i class="ti {{ $descriptionIcon }} text-[13px] text-primary"></i>
                    {{ $description }}
                </p>
            @endif
        </div>
        <span class="{{ $tone ?: 'bg-primary/10 text-primary' }} flex size-10 items-center justify-center rounded-lg">
            <i class="ti {{ $icon }} text-[18px]"></i>
        </span>
    </div>
</div>
