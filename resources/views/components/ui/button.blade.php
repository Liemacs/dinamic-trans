@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-700',
        'secondary' => 'bg-white text-brand-700 ring-1 ring-inset ring-line hover:bg-warm',
        'accent' => 'bg-lime text-forest hover:bg-accent-700',
        'tertiary' => 'text-brand-700 hover:bg-brand-50',
    ];

    // Every size clears the 44px minimum tap target required by WCAG 2.1 AA.
    $sizes = [
        'sm' => 'min-h-11 px-3.5 py-2 text-sm',
        'md' => 'min-h-11 px-5 py-2.5 text-sm',
        'lg' => 'min-h-12 px-6 py-3 text-base',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-button font-semibold',
        'transition-colors disabled:pointer-events-none disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
