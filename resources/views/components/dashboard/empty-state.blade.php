@props([
    'icon' => 'folder',
    'title',
    'description' => null,
])

{{-- What a section shows when it has nothing to show. The slot takes the action
     that would fix it. --}}

<div {{ $attributes->class(['flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="inline-flex size-12 items-center justify-center rounded-full bg-warm text-ink-400">
        <x-dashboard.icon :name="$icon" size="size-6" />
    </span>

    <h3 class="mt-4 text-sm font-semibold text-copy">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1.5 max-w-md text-sm leading-relaxed text-muted">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
