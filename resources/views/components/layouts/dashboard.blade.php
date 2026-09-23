@props([
    'title' => null,
    'description' => null,
])

{{--
    The shell: fixed sidebar, sticky topbar, one scrolling main.

    Ported from Hey Roger's components/layouts/dashboard.blade.php.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title.' · '.__('dashboard.brand') : __('dashboard.brand').' · '.__('dashboard.brand_subtitle') }}</title>

    {{-- The SVG is the one browsers prefer and the only one that stays sharp at
         any size; favicon.ico is there for the ones that ask for it by name
         regardless of what is declared here. --}}
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
{{--
    `h-dvh` + `overflow-hidden` keeps the chrome still and lets only <main>
    scroll, which is what makes a long table feel like an app rather than a page.
    dvh rather than vh so mobile browser chrome does not cut the sidebar off.
--}}
<body class="h-dvh overflow-hidden bg-warm font-ui text-copy antialiased">
    <div class="flex h-full" x-data="{ sidebarOpen: false }">
        <x-dashboard.sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-dashboard.topbar :$title :$description>
                {{ $actions ?? '' }}
            </x-dashboard.topbar>

            {{-- `container-type: size` publishes this element's height as `cqh` to
                 everything inside it, which is what lets a pinned side column size
                 itself to the space it actually has (see `dash-aside` in app.css).
                 Safe here because <main> is never sized by its content: its height
                 comes from `flex-1` against the `h-full` column, its width from the
                 row. --}}
            <main class="flex-1 overflow-y-auto [container-type:size]">
                <div class="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:space-y-8 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    {{-- The one confirmation dialog every destructive action opens (via the
         `confirm` Alpine store), in place of the browser's native popup. --}}
    <x-dashboard.confirm-dialog />

    {{-- Alpine ships inside Livewire's scripts and nothing else loads it (see the
         note at the top of resources/js/app.js), so the sidebar drawer and the
         nav accordion are dead without this — on every page, whether or not it
         renders a Livewire component. --}}
    @livewireScripts

    @stack('scripts')
</body>
</html>
