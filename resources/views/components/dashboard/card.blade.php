@props([
    'title' => null,
    'meta' => null,
    'padded' => true,
])

{{-- The one surface every block sits on. Anything that needs to break out of the
     padding (a full-bleed table) passes `:padded="false"` and handles its own. --}}

<section {{ $attributes->class(['rounded-panel border border-line bg-paper shadow-sm']) }}>
    @if ($title || $meta || isset($header))
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-5">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="truncate text-sm font-semibold text-copy">{{ $title }}</h2>
                @endif
                @if ($meta)
                    <p class="mt-0.5 truncate text-xs text-muted">{{ $meta }}</p>
                @endif
            </div>

            @isset($header)
                <div class="flex shrink-0 items-center gap-2">{{ $header }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-4 sm:p-5 lg:p-6' => $padded])>
        {{ $slot }}
    </div>
</section>
