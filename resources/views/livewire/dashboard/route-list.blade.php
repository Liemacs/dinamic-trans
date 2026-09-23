@php
    use App\Support\Format;
@endphp

<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-panel border border-line bg-lime/15 px-4 py-3 text-sm text-copy">{{ session('status') }}</div>
    @endif

    <x-dashboard.card :padded="false">
        <x-slot:header>
            {{-- Carries the search with it, so the file holds exactly the rows on
                 screen — all of them, not just the page being looked at. A plain
                 link rather than a Livewire action: a download is a response the
                 browser has to receive, not a component update. --}}
            @if ($routes->total() > 0)
                <a href="{{ route('dashboard.routes.excel', $search === '' ? [] : ['q' => $search]) }}"
                   class="inline-flex items-center gap-1.5 rounded-button border border-line bg-paper px-3.5 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm">
                    <x-dashboard.icon name="document" size="size-4" />
                    Excel
                </a>
            @endif

            <a href="{{ route('dashboard.calculator') }}"
               class="inline-flex items-center gap-1.5 rounded-button bg-forest px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-forest/90">
                <x-dashboard.icon name="plus" size="size-4" />
                Rută nouă
            </a>
        </x-slot:header>

        <x-slot:title>{{ $routes->total() }} {{ $routes->total() === 1 ? 'rută' : 'rute' }}</x-slot:title>

        <div class="border-b border-line px-4 py-3 sm:px-5">
            <label for="route-search" class="sr-only">Caută după localitate sau vehicul</label>
            <input
                id="route-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Caută după localitate, camion sau număr…"
                class="w-full rounded-button border border-line bg-paper px-3 py-2 text-sm text-copy transition-colors focus:border-forest focus:outline-none"
            >
        </div>

        @if ($routes->isEmpty())
            <x-dashboard.empty-state
                icon="route"
                :title="$search !== '' ? 'Nicio rută găsită' : 'Încă nicio rută salvată'"
                :description="$search !== ''
                    ? 'Nicio cursă nu are „'.$search.'” printre localități și niciun camion nu poartă numele sau numărul acesta.'
                    : 'Rutele calculate ajung aici, cu profitul și marja fiecăreia.'"
            >
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')"
                            class="rounded-button border border-line px-3.5 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm">
                        Șterge căutarea
                    </button>
                @else
                    <a href="{{ route('dashboard.calculator') }}"
                       class="inline-flex items-center gap-1.5 rounded-button bg-forest px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-forest/90">
                        <x-dashboard.icon name="plus" size="size-4" />
                        Deschide calculatorul
                    </a>
                @endif
            </x-dashboard.empty-state>
        @else
            {{-- One row open at a time. Which one is Alpine's business alone: the
                 detail is already rendered server-side, so opening it costs no
                 round trip and cannot go stale against the row above it. --}}
            <div x-data="{ open: null }" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3 sm:pl-5">Rută</th>
                            <th class="px-4 py-3 text-right">Distanță</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3 text-right">Venit</th>
                            <th class="px-4 py-3 text-right">Profit</th>
                            <th class="px-4 py-3 text-right">Marjă</th>
                            <th class="px-4 py-3 text-right sm:pr-5"><span class="sr-only">Acțiuni</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($routes as $route)
                            @php $costing = $route->costing(); @endphp

                            <tr wire:key="route-{{ $route->id }}" class="bg-paper transition-colors hover:bg-warm/60">
                                <td class="px-4 py-3 sm:pl-5">
                                    <div class="flex items-start gap-2">
                                        <button
                                            type="button"
                                            x-on:click="open = open === {{ $route->id }} ? null : {{ $route->id }}"
                                            :aria-expanded="open === {{ $route->id }} ? 'true' : 'false'"
                                            aria-label="Arată cifrele cu care a fost calculată ruta {{ $route->label() }}"
                                            class="mt-0.5 rounded text-ink-400 transition-colors hover:text-forest"
                                        >
                                            <svg
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                                class="size-4 transition-transform" aria-hidden="true"
                                                ::class="open === {{ $route->id }} ? 'rotate-90' : ''"
                                            >
                                                <path d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </button>

                                        <span class="min-w-0">
                                            <span class="block font-medium text-copy">{{ $route->label() }}</span>
                                            <span class="block text-xs text-muted">
                                                {{ $route->vehicle?->label() ?? 'Fără vehicul' }} · {{ $route->days }} {{ $route->days === 1 ? 'zi' : 'zile' }} · {{ $route->created_at?->format('d.m.Y') }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-copy">
                                    {{ Format::km($costing->totalDistanceKm()) }}
                                    @if ($costing->returnDistanceKm > 0)
                                        <span class="block text-xs text-muted">{{ Format::km($costing->distanceKm) }} dus + {{ Format::km($costing->returnDistanceKm) }} întors</span>
                                    @else
                                        <span class="block text-xs text-muted">doar dus</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted">{{ Format::money($costing->totalCost()) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($costing->revenue()) }}</td>
                                <td @class(['px-4 py-3 text-right font-medium tabular-nums', 'text-copy' => $costing->profit() >= 0, 'text-red-700' => $costing->profit() < 0])>
                                    {{ Format::money($costing->profit()) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted">{{ Format::percent($costing->margin()) }}</td>
                                <td class="px-4 py-3 text-right sm:pr-5">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- A link rather than a wire:click: the form
                                             is a screen of its own, and this way the
                                             row can be opened in a second tab. --}}
                                        <a
                                            href="{{ route('dashboard.routes.edit', $route) }}"
                                            class="rounded-button border border-line px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-copy transition-colors hover:bg-warm"
                                            aria-label="Modifică ruta {{ $route->label() }}"
                                        >Modifică</a>

                                        <x-dashboard.confirm-delete
                                            :message="'Ștergi ruta „'.$route->label().'”? Acțiunea nu poate fi anulată.'"
                                            :wire="'delete('.$route->id.')'"
                                        />
                                    </div>
                                </td>
                            </tr>

                            {{-- The figures the route was saved with, which are its
                                 own and are never recalculated from the current
                                 parameters. Rendered with the row rather than
                                 fetched on open, so there is nothing to go stale. --}}
                            <tr wire:key="route-detail-{{ $route->id }}" x-show="open === {{ $route->id }}" x-cloak class="bg-warm/50">
                                <td colspan="7" class="px-4 py-4 sm:px-5">
                                    <p class="text-xs font-semibold tracking-[0.08em] text-muted uppercase">Calculată cu</p>

                                    <div class="mt-3 grid gap-x-8 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <div>
                                            <p class="text-xs font-medium text-copy">Marfă</p>
                                            <dl class="mt-1.5 space-y-1 text-xs text-muted">
                                                <div class="flex justify-between gap-3">
                                                    <dt>Volum dus</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::number($costing->tonnes, 1) }} t × {{ Format::eur($costing->pricePerTonne) }}</dd>
                                                </div>
                                                @if ($costing->hasReturnLoad())
                                                    <div class="flex justify-between gap-3">
                                                        <dt>Volum retur</dt>
                                                        <dd class="tabular-nums text-copy">{{ Format::number($costing->returnTonnes, 1) }} t × {{ Format::eur($costing->returnPricePerTonne) }}</dd>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between gap-3">
                                                    <dt>Curs EUR</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::number($costing->eurRate, 2) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3 border-t border-line pt-1">
                                                    <dt>Preț rută</dt>
                                                    <dd class="tabular-nums font-medium text-copy">{{ Format::eur($costing->revenueEur()) }}</dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div>
                                            <p class="text-xs font-medium text-copy">Combustibil</p>
                                            <dl class="mt-1.5 space-y-1 text-xs text-muted">
                                                <div class="flex justify-between gap-3">
                                                    <dt>Consum</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::number($costing->consumption, 2) }} L/km</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Preț</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::money($costing->fuelPrice) }}/L</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Litri</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::litres($costing->fuelLitres()) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3 border-t border-line pt-1">
                                                    <dt>Cost</dt>
                                                    <dd class="tabular-nums font-medium text-copy">{{ Format::money($costing->fuelCost()) }}</dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div>
                                            <p class="text-xs font-medium text-copy">Șofer</p>
                                            <dl class="mt-1.5 space-y-1 text-xs text-muted">
                                                <div class="flex justify-between gap-3">
                                                    <dt>Zile</dt>
                                                    <dd class="tabular-nums text-copy">{{ $costing->days }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Prima zi</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::money($costing->driverFirstDay) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Zi adițională</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::money($costing->driverExtraDay) }}</dd>
                                                </div>
                                                @if ($costing->loadingDayBonus > 0)
                                                    <div class="flex justify-between gap-3">
                                                        <dt>Zi încărcare</dt>
                                                        <dd class="tabular-nums text-copy">{{ Format::money($costing->loadingDayBonus) }}</dd>
                                                    </div>
                                                @endif
                                                <div class="flex justify-between gap-3 border-t border-line pt-1">
                                                    <dt>Salariu</dt>
                                                    <dd class="tabular-nums font-medium text-copy">{{ Format::money($costing->driverSalary()) }}</dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div>
                                            <p class="text-xs font-medium text-copy">Drum</p>
                                            <dl class="mt-1.5 space-y-1 text-xs text-muted">
                                                <div class="flex justify-between gap-3">
                                                    <dt>Distanță</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::km($costing->distanceKm) }} + {{ Format::km($costing->returnDistanceKm) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Uzură</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::perKm($costing->wearPerKm) }} · {{ Format::money($costing->wearCost()) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Vamă</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::money($costing->customs) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3">
                                                    <dt>Rovinietă</dt>
                                                    <dd class="tabular-nums text-copy">{{ Format::money($costing->vignette) }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-3 border-t border-line pt-1">
                                                    <dt>Alte costuri</dt>
                                                    <dd class="tabular-nums font-medium text-copy">{{ Format::money($costing->otherCosts) }}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>

                                    @if ($route->notes)
                                        <p class="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-muted">{{ $route->notes }}</p>
                                    @endif

                                    <p class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-muted">
                                        <x-dashboard.icon name="check" size="size-4" class="mt-px shrink-0 text-ink-400" />
                                        Cifrele de mai sus aparțin acestei rute. Modificarea valorilor implicite sau a vehiculului nu le schimbă.
                                    </p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($routes->hasPages())
                <div class="border-t border-line px-4 py-3 sm:px-5">
                    {{ $routes->links() }}
                </div>
            @endif
        @endif
    </x-dashboard.card>
</div>
