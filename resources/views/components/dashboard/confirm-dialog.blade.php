{{--
    The one confirmation dialog. It carries no copy of its own — a destructive
    button fills the `confirm` store (title, message, and the closure to run on
    agree) and flips it open. One instance sits in the dashboard layout.

    Destructive by design: focus lands on Cancel, Escape and a backdrop click both
    cancel, and only a deliberate click on the red button runs the action.
--}}
<div
    x-data
    x-show="$store.confirm.open"
    x-effect="$store.confirm.open && $nextTick(() => $refs.cancel?.focus())"
    x-on:keydown.escape.window="$store.confirm.close()"
    style="display: none"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-message"
>
    {{-- Backdrop: the forest scrim, with a touch of blur so the table behind
         reads as "parked". --}}
    <div
        x-show="$store.confirm.open"
        x-transition.opacity.duration.150ms
        x-on:click="$store.confirm.close()"
        class="absolute inset-0 bg-forest/40 backdrop-blur-sm"
    ></div>

    <div
        x-show="$store.confirm.open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-[0.98]"
        class="relative w-full max-w-md rounded-panel border border-line bg-paper p-6 shadow-xl"
    >
        <div class="flex items-start gap-4">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                <x-dashboard.icon name="trash" />
            </span>

            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="text-base font-semibold text-copy" x-text="$store.confirm.title"></h2>
                <p id="confirm-dialog-message" class="mt-1 text-sm leading-relaxed text-muted" x-text="$store.confirm.message"></p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button
                type="button"
                x-ref="cancel"
                x-on:click="$store.confirm.close()"
                class="rounded-button border border-line px-4 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm focus:outline-none focus-visible:ring-2 focus-visible:ring-forest focus-visible:ring-offset-2"
            >{{ __('dashboard.actions.cancel') }}</button>
            <button
                type="button"
                x-on:click="$store.confirm.agree()"
                class="rounded-button bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2"
                x-text="$store.confirm.confirmLabel"
            ></button>
        </div>
    </div>
</div>
