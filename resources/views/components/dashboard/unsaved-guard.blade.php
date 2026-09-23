@props([
    // Input ids that count as work worth keeping. Any one of them holding a value
    // makes the form dirty.
    'fields' => [],
    // The Livewire action the "save and continue" button calls. It must return
    // true once the record is stored, so a failed validation can keep the user on
    // the page instead of navigating away from the errors.
    'save' => null,
    'title' => 'Ai date necompletate',
    'message' => 'Ruta nu a fost salvată. Dacă pleci acum, ce ai introdus se pierde.',
])

{{--
    Stands between a half-filled form and the next page.

    In-app links are intercepted and answered here. A reload or the Back button
    gets the browser's own confirmation instead — `beforeunload` cannot be styled
    and cannot wait for a save, which is a browser rule rather than a shortcut.
    See `unsavedGuard` in resources/js/app.js.
--}}
<div x-data="unsavedGuard({ fields: @js($fields), save: @js($save) })">
    <div
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="cancel()"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="unsaved-guard-title"
        aria-describedby="unsaved-guard-message"
    >
        {{-- Clicking the backdrop cancels: it is the harmless answer, and the two
             consequential ones are both deliberate button presses. --}}
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            x-on:click="cancel()"
            class="absolute inset-0 bg-forest/40 backdrop-blur-sm"
        ></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-[0.98]"
            class="relative w-full max-w-md rounded-panel border border-line bg-paper p-6 shadow-xl"
        >
            <div class="flex items-start gap-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-lime-soft text-forest">
                    <x-dashboard.icon name="warning" />
                </span>

                <div class="min-w-0">
                    <h2 id="unsaved-guard-title" class="text-base font-semibold text-copy">{{ $title }}</h2>
                    <p id="unsaved-guard-message" class="mt-1 text-sm leading-relaxed text-muted">{{ $message }}</p>
                </div>
            </div>

            {{-- Stacked rather than in a row: three actions side by side at this
                 width push "Pleacă fără salvare" into two lines, and the wrapped
                 one is the destructive one. --}}
            <div class="mt-6 space-y-2">
                @if ($save)
                    <button
                        type="button"
                        x-ref="save"
                        x-on:click="saveAndLeave()"
                        x-bind:disabled="saving"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90 disabled:opacity-60"
                    >
                        <x-dashboard.icon name="check" size="size-4" />
                        <span x-show="! saving">Salvează și continuă</span>
                        <span x-show="saving" x-cloak>Se salvează…</span>
                    </button>
                @endif

                <button
                    type="button"
                    x-on:click="discardAndLeave()"
                    x-bind:disabled="saving"
                    class="w-full rounded-button border border-line px-4 py-2.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-50 disabled:opacity-60"
                >Pleacă fără salvare</button>

                <button
                    type="button"
                    x-on:click="cancel()"
                    x-bind:disabled="saving"
                    class="w-full rounded-button px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:bg-warm hover:text-copy disabled:opacity-60"
                >Rămân pe pagină</button>
            </div>
        </div>
    </div>
</div>
