{{--
    Two presentations of the same nav: a column that is always there from `lg`
    up, and a drawer below it.

    `sidebarOpen` belongs to the layout's Alpine scope
    (components/layouts/dashboard.blade.php), which wraps both this and the
    topbar's toggle — so neither needs to know about the other.

    Collapsible to an icons-only rail. The collapsed state is the `sidebar`
    Alpine store (resources/js/app.js) — a store rather than x-data so the toggle
    inside the nav can write it and the choice is remembered per browser. The nav
    is told it may collapse; the drawer below is not.
--}}

<aside
    :class="{ 'w-[76px]': $store.sidebar?.collapsed, 'w-64': ! $store.sidebar?.collapsed }"
    class="hidden w-64 shrink-0 border-r border-line bg-paper transition-[width] duration-200 ease-out lg:block"
>
    <x-dashboard.nav :collapsible="true" />
</aside>

<div class="lg:hidden">
    {{-- Scrim. Clicking it closes the drawer; it is not focusable, and the drawer
         itself carries the labelled close button. --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-forest/40"
        aria-hidden="true"
    ></div>

    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        @keydown.escape.window="sidebarOpen = false"
        class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-line bg-paper"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('dashboard.a11y.primary_navigation') }}"
    >
        <button
            type="button"
            @click="sidebarOpen = false"
            class="tap-target absolute top-4 right-3 inline-flex items-center justify-center rounded-xl text-ink-400 transition-colors hover:text-forest"
            aria-label="{{ __('dashboard.actions.close_menu') }}"
        >
            <x-dashboard.icon name="close" />
        </button>

        <x-dashboard.nav />
    </div>
</div>
