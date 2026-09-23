@props([
    // The line shown in the dialog, e.g. 'Ștergi ruta „Cluj → Milano”?'
    'message',
    'title' => null,
    'confirmLabel' => null,
    // A Livewire call to run on confirm, e.g. "delete(5)" → $wire.delete(5).
    // Omit it and the button instead submits the <form> it sits inside.
    'wire' => null,
])

@php
    $title ??= __('dashboard.actions.delete');
    $confirmLabel ??= __('dashboard.actions.delete');

    /*
     * The closure the dialog runs if the user agrees, built here so the markup
     * stays declarative. It is wrapped in an IIFE that captures its target the
     * moment the button is clicked — the form element, or this component's $wire
     * — so the deferred call still lands on the right place once the dialog
     * resolves. Only the developer-set `wire` string reaches the output raw; the
     * copy goes through @js.
     */
    $action = $wire
        ? '(() => { const w = $wire; return () => w.'.$wire.'; })()'
        : "(() => { const f = \$el.closest('form'); return () => f.submit(); })()";
@endphp

{{-- Double-quoted attribute: @js emits single-quoted JS literals (with any
     apostrophe hex-escaped), and the action code only uses single quotes, so
     nothing collides with the delimiter. --}}
<button
    type="button"
    x-data
    x-on:click="$store.confirm.ask({ title: @js($title), message: @js($message), confirmLabel: @js($confirmLabel), action: {!! $action !!} })"
    {{ $attributes->class('rounded-button border border-line px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50') }}
>{{ $slot->isEmpty() ? __('dashboard.actions.delete') : $slot }}</button>
