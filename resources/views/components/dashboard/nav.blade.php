@props([
    // The desktop copy collapses to an icons-only rail; the mobile drawer is
    // always full. When true, the collapse bindings read the `sidebar` Alpine
    // store (resources/js/app.js), which owns the state and persists it.
    'collapsible' => false,
])

@php
    use App\Support\DashboardNav;

    $items = DashboardNav::items();

    /*
     * Groups behave as an accordion — one open at a time — and the group holding
     * the current page starts open. Alpine owns that state so switching groups
     * costs no round trip; which group is *current* is decided server-side from
     * the route name, so the sidebar is already right in the first paint.
     */
    $currentGroup = null;

    foreach ($items as $item) {
        if ($item['type'] === 'group' && DashboardNav::isCurrent($item)) {
            $currentGroup = $item['key'];
        }
    }
@endphp

{{--
    The sidebar's contents, rendered twice by components/dashboard/sidebar: once
    in the fixed desktop column and once inside the mobile drawer. Each copy gets
    its own Alpine scope, so their accordions are independent — which is what you
    want, since only one of them is ever on screen.
--}}
<div class="flex h-full flex-col gap-6 px-3 py-5" x-data="{ group: @js($currentGroup) }">
    <a href="{{ route('dashboard.overview') }}" class="flex items-center gap-3 rounded-panel px-1 py-1" @if ($collapsible) :class="$store.sidebar?.collapsed && 'justify-center'" @endif>
        {{-- The brand mark: the app's initials on the forest tile, so the shell
             has an identity without waiting on an uploaded logo.

             Taken from the name rather than typed, or renaming the app leaves the
             old initials sitting in the corner. --}}
        @php
            $initials = collect(preg_split('/\s+/', __('dashboard.brand')) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');
        @endphp
        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-forest text-sm font-semibold text-lime">{{ $initials }}</span>
        <span class="min-w-0" @if ($collapsible) x-show="! $store.sidebar?.collapsed" @endif>
            <span class="block truncate text-sm font-semibold text-copy">{{ __('dashboard.brand') }}</span>
            <span class="block truncate text-xs text-muted">{{ __('dashboard.brand_subtitle') }}</span>
        </span>
    </a>

    <nav class="flex-1 space-y-1 overflow-y-auto" aria-label="{{ __('dashboard.a11y.primary_navigation') }}">
        @foreach ($items as $item)
            @php
                $children = $item['type'] === 'group' ? ($item['children'] ?? []) : [];
                $current = DashboardNav::isCurrent($item);
            @endphp

            @if ($item['type'] === 'link')
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors',
                        'bg-forest font-semibold text-white' => $current,
                        'text-ink-600 hover:bg-soft hover:text-forest' => ! $current,
                    ])
                    @if ($current) aria-current="page" @endif
                    @if ($collapsible)
                        :class="$store.sidebar?.collapsed && 'justify-center'"
                        x-bind:title="$store.sidebar?.collapsed ? @js(DashboardNav::label($item['key'])) : null"
                    @endif
                >
                    <x-dashboard.icon :name="$item['icon']" class="shrink-0 {{ $current ? 'text-lime' : 'text-ink-400' }}" />
                    <span class="truncate" @if ($collapsible) x-show="! $store.sidebar?.collapsed" @endif>{{ DashboardNav::label($item['key']) }}</span>
                </a>
            @else
                <div>
                    <button
                        type="button"
                        {{-- No braces on the toggle: without the collapsible
                             prefix this is all Alpine gets, and Alpine evaluates
                             a handler that does not start with `if` in return
                             position — a leading `{` would parse as an object
                             literal, not a block. --}}
                        @click="@if ($collapsible)if ($store.sidebar?.collapsed) { $store.sidebar?.set(false); group = '{{ $item['key'] }}' } else @endif group = group === '{{ $item['key'] }}' ? null : '{{ $item['key'] }}'"
                        :aria-expanded="group === '{{ $item['key'] }}' ? 'true' : 'false'"
                        aria-label="{{ __('dashboard.actions.toggle_section', ['label' => DashboardNav::label($item['key'])]) }}"
                        @class([
                            'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-colors',
                            'font-semibold text-forest' => $current,
                            'text-ink-600 hover:bg-soft hover:text-forest' => ! $current,
                        ])
                        @if ($collapsible) :class="$store.sidebar?.collapsed && 'justify-center'" @endif
                    >
                        <x-dashboard.icon :name="$item['icon']" class="shrink-0 {{ $current ? 'text-forest' : 'text-ink-400' }}" />
                        <span class="flex-1 truncate" @if ($collapsible) x-show="! $store.sidebar?.collapsed" @endif>{{ DashboardNav::label($item['key']) }}</span>
                        {{-- Inline rather than <x-dashboard.icon>: a Blade @if
                             inside a component tag breaks the component parser,
                             and this chevron needs one to hide on the rail. --}}
                        <svg
                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            class="size-4 shrink-0 text-ink-400 transition-transform"
                            aria-hidden="true"
                            ::class="group === '{{ $item['key'] }}' ? 'rotate-0' : '-rotate-90'"
                            @if ($collapsible) x-show="! $store.sidebar?.collapsed" @endif
                        >
                            <path d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    {{-- x-show for the open/close, x-collapse for the height
                         animation (Alpine's own plugin, bundled with Livewire).
                         The open group renders open, so it needs no x-cloak; the
                         closed ones do, or they flash into view before Alpine
                         boots. Never open on the collapsed rail — there is no room
                         for it. --}}
                    <div
                        x-show="group === '{{ $item['key'] }}'@if ($collapsible) && ! $store.sidebar?.collapsed @endif"
                        x-collapse
                        @unless ($current) x-cloak @endunless
                        class="mt-1 ml-5 space-y-0.5 border-l border-line pl-3"
                    >
                        @foreach ($children as $child)
                            @php $childCurrent = DashboardNav::isCurrent($child); @endphp

                            <a
                                href="{{ route($child['route']) }}"
                                @class([
                                    'block truncate rounded-lg px-3 py-2 text-sm transition-colors',
                                    'bg-lime-soft font-semibold text-forest' => $childCurrent,
                                    'text-muted hover:bg-soft hover:text-forest' => ! $childCurrent,
                                ])
                                @if ($childCurrent) aria-current="page" @endif
                            >
                                {{ DashboardNav::label($child['key']) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    @if ($collapsible)
        <div class="space-y-1 border-t border-line pt-4">
            {{-- Collapse the rail to icons only; the choice is remembered per
                 browser (the `sidebar` store persists it to localStorage). --}}
            <button
                type="button"
                @click="$store.sidebar?.toggle()"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-600 transition-colors hover:bg-soft hover:text-forest"
                :class="$store.sidebar?.collapsed && 'justify-center'"
                x-bind:aria-label="$store.sidebar?.collapsed ? @js(__('dashboard.actions.expand_sidebar')) : @js(__('dashboard.actions.collapse_sidebar'))"
            >
                <x-dashboard.icon name="chevron-right" class="shrink-0 text-ink-400 transition-transform" ::class="$store.sidebar?.collapsed ? '' : 'rotate-180'" />
                <span class="truncate" x-show="! $store.sidebar?.collapsed">{{ __('dashboard.actions.collapse_sidebar') }}</span>
            </button>
        </div>
    @endif
</div>
