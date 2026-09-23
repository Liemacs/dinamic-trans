{{--
    The paginator, in the dashboard's own palette rather than Laravel's default
    grey. Overrides vendor/laravel/framework/.../tailwind.blade.php, which is the
    view the framework reaches for unless told otherwise.

    Two presentations of the same thing: Înapoi/Înainte alone on a phone, and the
    numbered pages from `sm` up, where there is room for them.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
        {{-- Phones: the two arrows, nothing else. --}}
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="cursor-default rounded-button border border-line px-3 py-2 text-sm text-ink-400">{!! __('pagination.previous') !!}</span>
            @else
                <button type="button" wire:click="previousPage" wire:loading.attr="disabled" rel="prev"
                        class="rounded-button border border-line bg-paper px-3 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm">{!! __('pagination.previous') !!}</button>
            @endif

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage" wire:loading.attr="disabled" rel="next"
                        class="rounded-button border border-line bg-paper px-3 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm">{!! __('pagination.next') !!}</button>
            @else
                <span class="cursor-default rounded-button border border-line px-3 py-2 text-sm text-ink-400">{!! __('pagination.next') !!}</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between sm:gap-4">
            <p class="text-xs text-muted">
                {!! __('Showing') !!}
                <span class="font-medium text-copy">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-medium text-copy">{{ $paginator->lastItem() }}</span>
                {!! __('of') !!}
                <span class="font-medium text-copy">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="tap-target inline-flex items-center justify-center rounded-button border border-line px-2.5 text-ink-300" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <x-dashboard.icon name="chevron-right" size="size-4" class="rotate-180" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" rel="prev"
                            class="tap-target inline-flex items-center justify-center rounded-button border border-line bg-paper px-2.5 text-ink-500 transition-colors hover:bg-warm hover:text-forest"
                            aria-label="{{ __('pagination.previous') }}">
                        <x-dashboard.icon name="chevron-right" size="size-4" class="rotate-180" />
                    </button>
                @endif

                @foreach ($elements as $element)
                    {{-- The gap the paginator leaves where it has elided pages. --}}
                    @if (is_string($element))
                        <span class="px-2 text-sm text-ink-400" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex min-w-9 items-center justify-center rounded-button bg-forest px-3 py-2 text-sm font-semibold text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }})" wire:loading.attr="disabled"
                                        class="inline-flex min-w-9 items-center justify-center rounded-button border border-line bg-paper px-3 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" rel="next"
                            class="tap-target inline-flex items-center justify-center rounded-button border border-line bg-paper px-2.5 text-ink-500 transition-colors hover:bg-warm hover:text-forest"
                            aria-label="{{ __('pagination.next') }}">
                        <x-dashboard.icon name="chevron-right" size="size-4" />
                    </button>
                @else
                    <span class="tap-target inline-flex items-center justify-center rounded-button border border-line px-2.5 text-ink-300" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <x-dashboard.icon name="chevron-right" size="size-4" />
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
