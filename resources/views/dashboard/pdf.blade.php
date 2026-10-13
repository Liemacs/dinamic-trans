@php
    use App\Support\DashboardNav;
@endphp

{{--
    Joining PDFs. Plain Blade and Alpine, with no Livewire component behind it:
    the files never reach the server, so there is no server state to keep. The
    work is done by `pdfMerge` in resources/js/app.js.
--}}
<x-layouts.dashboard
    :title="DashboardNav::heading('pdf')"
    :description="DashboardNav::description('pdf')"
>
    <div x-data="pdfMerge" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div class="space-y-4">
            {{-- The drop zone is a label around the file input, so a click anywhere
                 on it opens the picker and the keyboard reaches it like any field. --}}
            <label
                x-on:dragover.prevent="over = true"
                x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; add($event.dataTransfer.files)"
                x-bind:class="over ? 'border-forest bg-lime-soft/60' : 'border-ink-300 bg-paper hover:border-forest'"
                class="flex cursor-pointer flex-col items-center rounded-panel border-2 border-dashed px-6 py-10 text-center transition-colors focus-within:border-forest"
            >
                <span class="inline-flex size-12 items-center justify-center rounded-full bg-warm text-ink-500">
                    <x-dashboard.icon name="plus" size="size-6" />
                </span>
                <span class="mt-4 text-sm font-semibold text-copy">Trage fișierele PDF aici</span>
                <span class="mt-1 text-sm text-muted">sau apasă ca să le alegi de pe calculator</span>

                <input
                    type="file"
                    accept="application/pdf,.pdf"
                    multiple
                    class="sr-only"
                    x-on:change="add($event.target.files); $event.target.value = ''"
                >
            </label>

            <p x-show="error" x-cloak x-text="error" role="alert" class="rounded-panel border border-line bg-red-50 px-4 py-3 text-sm text-red-700"></p>
            <p x-show="done" x-cloak role="status" class="rounded-panel border border-line bg-lime/15 px-4 py-3 text-sm text-copy">
                Descărcat: <span class="font-medium" x-text="done"></span>
            </p>

            <x-dashboard.card :padded="false" x-show="files.length > 0" x-cloak>
                <x-slot:title><span x-text="files.length === 1 ? '1 fișier' : files.length + ' fișiere'"></span></x-slot:title>
                <x-slot:meta>Ordinea de aici este ordinea paginilor în fișierul final.</x-slot:meta>
                <x-slot:header>
                    <button type="button" x-on:click="clear()" class="rounded-button border border-line px-2.5 py-1.5 text-xs font-medium text-copy transition-colors hover:bg-warm">
                        Golește lista
                    </button>
                </x-slot:header>

                <ul class="divide-y divide-line">
                    <template x-for="(file, index) in files" :key="file.id">
                        <li
                            draggable="true"
                            x-on:dragstart="dragging = index; $event.dataTransfer.effectAllowed = 'move'"
                            x-on:dragover.prevent="dragOver(index)"
                            x-on:dragend="dragging = null"
                            x-on:drop.prevent.stop="dragging = null"
                            x-bind:class="dragging === index ? 'opacity-40' : ''"
                            class="flex items-center gap-3 px-4 py-3 sm:px-5"
                        >
                            <span class="w-6 shrink-0 text-center text-sm font-semibold text-muted tabular-nums" x-text="index + 1"></span>

                            <span class="min-w-0 flex-1 cursor-grab">
                                <span class="block truncate text-sm font-medium text-copy" x-text="file.name"></span>
                                <span class="block text-xs" x-bind:class="file.error ? 'text-red-700' : 'text-muted'">
                                    <span x-show="file.error" x-text="file.error"></span>
                                    <span x-show="!file.error && file.pages === null">Se citește…</span>
                                    <span x-show="!file.error && file.pages !== null" x-text="(file.pages === 1 ? '1 pagină' : file.pages + ' pagini') + ' · ' + size(file.bytes)"></span>
                                </span>
                            </span>

                            <span class="flex shrink-0 items-center gap-1">
                                <button type="button" x-on:click="move(index, -1)" x-bind:disabled="index === 0" x-bind:aria-label="'Mută mai sus ' + file.name"
                                        class="tap-target inline-flex items-center justify-center rounded-button border border-line text-ink-500 transition-colors hover:bg-warm hover:text-forest disabled:opacity-30">
                                    <x-dashboard.icon name="chevron-down" size="size-4" class="rotate-180" />
                                </button>
                                <button type="button" x-on:click="move(index, 1)" x-bind:disabled="index === files.length - 1" x-bind:aria-label="'Mută mai jos ' + file.name"
                                        class="tap-target inline-flex items-center justify-center rounded-button border border-line text-ink-500 transition-colors hover:bg-warm hover:text-forest disabled:opacity-30">
                                    <x-dashboard.icon name="chevron-down" size="size-4" />
                                </button>
                                <button type="button" x-on:click="remove(file.id)" x-bind:aria-label="'Scoate ' + file.name"
                                        class="tap-target inline-flex items-center justify-center rounded-button border border-line text-red-700 transition-colors hover:bg-red-50">
                                    <x-dashboard.icon name="trash" size="size-4" />
                                </button>
                            </span>
                        </li>
                    </template>
                </ul>
            </x-dashboard.card>
        </div>

        <div class="dash-aside space-y-4">
            <x-dashboard.card title="Fișierul final">
                <label for="pdf-name" class="block text-sm font-medium text-copy">Nume</label>
                <div class="relative mt-1.5">
                    <input id="pdf-name" type="text" x-model="name"
                           class="w-full rounded-button border border-line bg-paper py-2 pr-12 pl-3 text-sm text-copy transition-colors focus:border-forest focus:outline-none">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted">.pdf</span>
                </div>

                <dl class="mt-5 space-y-2.5 border-t border-line pt-4 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">Fișiere</dt>
                        <dd class="font-medium text-copy tabular-nums" x-text="files.length"></dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">Pagini</dt>
                        <dd class="font-medium text-copy tabular-nums" x-text="pageCount"></dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs leading-relaxed text-muted" x-show="files.length < 2">Adaugă cel puțin două fișiere.</p>
                <p class="mt-4 text-xs leading-relaxed text-red-700" x-show="files.some((file) => file.error)" x-cloak>
                    Scoate din listă fișierele marcate cu roșu ca să poți uni restul.
                </p>
            </x-dashboard.card>

            <x-dashboard.card title="Unde ajung fișierele">
                <p class="text-sm leading-relaxed text-muted">
                    Nicăieri. Unirea se face pe calculatorul tău, în browser — fișierele nu
                    sunt trimise pe server și nu rămân salvate în aplicație.
                </p>
            </x-dashboard.card>

            <div class="dash-aside-actions">
                <button
                    type="button"
                    x-on:click="merge()"
                    x-bind:disabled="!ready"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90 disabled:opacity-50"
                >
                    <x-dashboard.icon name="download" size="size-4" />
                    <span x-show="!busy">Unește și descarcă</span>
                    <span x-show="busy" x-cloak>Se unește…</span>
                </button>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
