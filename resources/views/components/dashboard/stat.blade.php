@props([
    'label',
    'value',
    'unit' => null,
    'icon' => null,
    // 'default' | 'accent' — accent inverts the tile onto forest with the figure
    // in lime, the treatment the Hey Roger calculator gives its result panel.
    'tone' => 'default',
    'hint' => null,
])

@php
    $accent = $tone === 'accent';
@endphp

<div @class([
    'flex flex-col justify-between gap-4 rounded-panel border p-4 sm:p-5',
    'border-forest bg-forest' => $accent,
    'border-line bg-paper shadow-sm' => ! $accent,
])>
    <div class="flex items-start justify-between gap-3">
        <p @class(['text-xs font-medium tracking-[0.08em] uppercase', 'text-lime-soft' => $accent, 'text-muted' => ! $accent])>{{ $label }}</p>
        @if ($icon)
            <span @class([
                'inline-flex size-8 shrink-0 items-center justify-center rounded-lg',
                'bg-white/10 text-lime' => $accent,
                'bg-warm text-ink-500' => ! $accent,
            ])>
                <x-dashboard.icon :name="$icon" size="size-4" />
            </span>
        @endif
    </div>

    <div>
        <p class="flex flex-wrap items-baseline gap-1.5">
            <span @class(['text-2xl font-semibold tracking-tight sm:text-3xl', 'text-lime' => $accent, 'text-copy' => ! $accent])>{{ $value }}</span>
            @if ($unit)
                <span @class(['text-sm', 'text-white/70' => $accent, 'text-muted' => ! $accent])>{{ $unit }}</span>
            @endif
        </p>
        @if ($hint)
            <p @class(['mt-1 text-xs', 'text-white/60' => $accent, 'text-muted' => ! $accent])>{{ $hint }}</p>
        @endif
    </div>
</div>
