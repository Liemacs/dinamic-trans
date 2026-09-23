@props([
    'title' => null,
    // Where the "back" link goes; the printable page is always opened from
    // somewhere.
    'back' => null,
])

{{--
    A bare sheet of paper.

    The dashboard shell is a fixed sidebar around one scrolling pane, which is
    exactly the layout a printer cannot make sense of. Rather than talk the
    browser out of it with a page of print overrides, the printable report gets
    its own page: nothing on it but the sheet, and the two controls that are
    hidden the moment it reaches paper.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title.' · '.__('dashboard.brand') : __('dashboard.brand') }}</title>

    {{-- The SVG is the one browsers prefer and the only one that stays sharp at
         any size; favicon.ico is there for the ones that ask for it by name
         regardless of what is declared here. --}}
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-warm font-ui text-copy antialiased print:bg-white">
    <div class="mx-auto w-full max-w-[1123px] px-4 py-6 print:max-w-none print:p-0">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            @if ($back)
                <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors hover:text-forest">
                    <x-dashboard.icon name="chevron-right" size="size-4" class="rotate-180" />
                    Înapoi la raport
                </a>
            @endif

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90"
            >
                <x-dashboard.icon name="printer" size="size-4" />
                Tipărește
            </button>
        </div>

        <div class="rounded-panel border border-line bg-paper p-5 shadow-sm sm:p-8 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            {{ $slot }}
        </div>
    </div>

    {{-- The button said "generate the report", so the print dialog is the next
         step, not a second click. Dismissing it leaves the sheet on screen with
         the button above it. --}}
    <script>
        window.addEventListener('load', () => window.setTimeout(() => window.print(), 200));
    </script>
</body>
</html>
