@props([
    'title' => null,
    'description' => null,
])

@php
    // No auth is wired yet, so there is no user to name here. The block below
    // renders the moment one exists; until then the topbar is heading + actions.
    $user = auth()->user();

    $initials = collect(preg_split('/\s+/', trim((string) $user?->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="shrink-0 border-b border-line bg-paper">
    <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="tap-target -ml-2 inline-flex items-center justify-center rounded-xl text-ink-500 transition-colors hover:text-forest lg:hidden"
                aria-label="{{ __('dashboard.actions.open_menu') }}"
            >
                <x-dashboard.icon name="menu" size="size-6" />
            </button>

            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold tracking-tight text-copy sm:text-2xl">
                    {{ $title ?? __('dashboard.brand_subtitle') }}
                </h1>
                @if ($description)
                    <p class="mt-0.5 truncate text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            {{ $slot }}

            @if ($user)
                <div class="flex items-center gap-3 border-l border-line pl-3">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-warm text-xs font-semibold text-forest">
                        {{ $initials !== '' ? $initials : '—' }}
                    </span>
                    <span class="hidden min-w-0 md:block">
                        <span class="block truncate text-sm font-medium text-copy">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
                    </span>
                </div>
            @endif
        </div>
    </div>
</header>
