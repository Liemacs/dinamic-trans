@php
    use App\Models\Setting;
    use App\Support\Format;

    $costing = $this->costing;

    /*
     * Before a distance is in, the parameters alone (customs, the vignette, a
     * driver's first day) already add up to a loss — and showing that as a red
     * figure reads as a verdict on a route nobody has described yet. So an
     * incomplete form shows a dash, and nothing is coloured until there is
     * something to colour.
     */
    $ready = $costing->isComplete();
    $profitable = ! $ready || $costing->profit() >= 0;
    $lei = Setting::currency();
@endphp

{{-- The editing screen's two columns: a wide form, and a narrow column pinned
     beside it holding the result and the Save row (see `dash-aside` in
     resources/css/app.css). Below lg the column simply falls under the form. --}}
<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-panel border border-line bg-lime/15 px-4 py-3 text-sm text-copy">{{ session('status') }}</div>
        @endif

        {{-- On a saved route the boxes below are not a fresh calculation but the
             figures that route was quoted at, so the screen says so before the
             first field. --}}
        @if ($editing)
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-panel border border-line bg-warm px-4 py-3">
                <span class="text-xs leading-relaxed text-muted">
                    Modifici o rută salvată. Cifrele de mai jos sunt ale ei, iar salvarea le înlocuiește.
                </span>
                <a href="{{ route('dashboard.routes') }}" class="text-xs font-medium text-forest underline underline-offset-2">Înapoi la rute salvate</a>
            </div>
        @endif

        <x-dashboard.card title="Cursa" meta="De unde, până unde și cu ce.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field model="origin" label="Plecare" placeholder="Hâncești" required />
                <x-dashboard.field model="destination" label="Destinație" placeholder="Brăila" required />

                <x-dashboard.field
                    model="distance_km"
                    label="Distanță dus"
                    type="number"
                    suffix="km"
                    step="1"
                    min="0"
                    help="Doar drumul până la destinație."
                    live
                    required
                />

                <x-dashboard.field
                    model="return_distance_km"
                    label="Distanță întors"
                    type="number"
                    suffix="km"
                    step="1"
                    min="0"
                    :help="$has_return_load
                        ? 'Prin punctul de încărcare, până acasă.'
                        : 'Drumul de întoarcere, gol. Se completează singur după dus.'"
                    live
                />

                <x-dashboard.field
                    model="vehicle_id"
                    label="Vehicul"
                    type="select"
                    live
                    required
                    :help="$this->selectedVehicle ? 'Consumul de mai jos este al lui.' : 'Alege un camion ca să preia consumul lui.'"
                >
                    <option value="">Alege un vehicul…</option>
                    @foreach ($this->vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->label() }}</option>
                    @endforeach
                </x-dashboard.field>
            </div>

            {{-- With no truck on file there is nothing to choose, and the route
                 cannot be saved. Better to say so here than to let someone fill
                 the whole form and meet the error at the end. --}}
            @if ($this->vehicles->isEmpty())
                <div class="mt-4 flex items-start gap-2.5 rounded-button border border-line bg-warm px-4 py-3 text-xs leading-relaxed text-muted">
                    <x-dashboard.icon name="warning" size="size-4" class="mt-px shrink-0 text-ink-400" />
                    <span>
                        Nu ai niciun vehicul activ, iar o rută nu poate fi salvată fără unul.
                        <a href="{{ route('dashboard.fleet.vehicles') }}" class="font-medium text-forest underline underline-offset-2">Adaugă un camion</a>
                        și revino.
                    </span>
                </div>
            @endif

            {{-- What choosing a truck actually did. The consumption field it fills lives
                 in a card further down, off the screen at this scroll position —
                 so without this the select looks like it does nothing. --}}
            @if ($truck = $this->selectedVehicle)
                <div class="mt-4 rounded-button border border-line bg-warm px-4 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="text-xs font-medium tracking-[0.08em] text-muted uppercase">Preluat din {{ $truck->name }}</span>
                        <span class="text-sm text-copy">
                            <span class="font-semibold">{{ Format::number($truck->consumption, 2) }} L/km</span>
                            <span class="text-muted">consum</span>
                        </span>
                    </div>

                </div>
            @endif

            {{-- Fuel and wear are charged on the sum, so the sum is shown rather
                 than left to be worked out from the two boxes above. --}}
            <div class="mt-4 flex flex-wrap items-baseline justify-between gap-3 rounded-button border border-line bg-warm px-4 py-3">
                <span class="text-xs font-medium tracking-[0.08em] text-muted uppercase">Distanță totală</span>
                <span class="text-sm text-copy">
                    <span class="text-muted">{{ Format::km($costing->distanceKm) }} + {{ Format::km($costing->returnDistanceKm) }} =</span>
                    <span class="font-semibold">{{ Format::km($costing->totalDistanceKm()) }}</span>
                </span>
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Marfa și tariful" meta="Prețul se cotează în euro și se încasează în {{ $lei }}.">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-dashboard.field model="tonnes" label="Volum" type="number" suffix="tone" step="0.5" min="0" live />
                <x-dashboard.field model="price_per_tonne" label="Preț per tonă" type="number" suffix="€" step="1" min="0" live />
                <x-dashboard.field model="eur_rate" label="Curs EUR/{{ $lei }}" type="number" step="0.01" min="0" live required />
            </div>

            <div class="mt-4 flex flex-wrap items-baseline justify-between gap-3 rounded-button border border-line bg-warm px-4 py-3">
                <span class="text-xs font-medium tracking-[0.08em] text-muted uppercase">Marfă dus</span>
                <span class="text-sm text-copy">
                    <span class="font-semibold">{{ Format::eur($costing->outboundRevenueEur()) }}</span>
                    @unless ($costing->hasReturnLoad())
                        <span class="text-muted">→</span>
                        <span class="font-semibold">{{ Format::money($costing->revenue()) }}</span>
                    @endunless
                </span>
            </div>
        </x-dashboard.card>

        {{-- Off by default: most runs come home empty, and six fields for the
             exception would crowd the ones used every time. --}}
        <x-dashboard.card title="Încărcătură de retur" meta="Când camionul nu se întoarce gol.">
            <label class="flex items-center gap-2.5 text-sm text-copy">
                <input type="checkbox" wire:model.live="has_return_load" class="size-4 rounded border-line accent-forest">
                Camionul încarcă marfă pentru drumul de întoarcere
            </label>

            @if ($has_return_load)
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-dashboard.field
                        model="return_destination"
                        label="Punct de încărcare"
                        placeholder="Galați"
                        help="A treia destinație. Trece distanța prin ea la „Distanță întors”."
                        required
                    />
                    <x-dashboard.field model="return_tonnes" label="Volum retur" type="number" suffix="tone" step="0.5" min="0" live />
                    <x-dashboard.field model="return_price_per_tonne" label="Preț per tonă retur" type="number" suffix="€" step="1" min="0" live />
                    <x-dashboard.field
                        model="loading_day_bonus"
                        label="Plată zi încărcare"
                        type="number"
                        :suffix="$lei"
                        step="100"
                        min="0"
                        help="Peste tariful zilei, pentru munca de încărcare."
                        live
                    />
                </div>

                <div class="mt-4 flex flex-wrap items-baseline justify-between gap-3 rounded-button border border-line bg-warm px-4 py-3">
                    <span class="text-xs font-medium tracking-[0.08em] text-muted uppercase">Venit retur</span>
                    <span class="text-sm text-copy">
                        <span class="font-semibold">{{ Format::eur($costing->returnRevenueEur()) }}</span>
                        <span class="text-muted">→</span>
                        <span class="font-semibold">{{ Format::money($costing->returnRevenueEur() * $costing->eurRate) }}</span>
                    </span>
                </div>
            @endif
        </x-dashboard.card>

        <x-dashboard.card title="Șoferul" meta="Prima zi la tariful ei, fiecare zi următoare la cel adițional.">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-dashboard.field model="days" label="Zile" type="number" suffix="zile" step="1" min="1" max="60" live required />
                <x-dashboard.field model="driver_first_day" label="Prima zi" type="number" :suffix="$lei" step="50" min="0" live />
                <x-dashboard.field model="driver_extra_day" label="Zi adițională" type="number" :suffix="$lei" step="50" min="0" live />
            </div>

            {{-- The formula written out against the numbers in the boxes: the
                 quickest way to catch a day counted twice. --}}
            @php $days = max(1, (int) $this->days); @endphp
            <div class="mt-4 flex flex-wrap items-baseline justify-between gap-3 rounded-button border border-line bg-warm px-4 py-3">
                <span class="text-xs text-muted">
                    {{ Format::money($costing->driverFirstDay) }}
                    @if ($days > 1)
                        + {{ $days - 1 }} × {{ Format::money($costing->driverExtraDay) }}
                    @endif
                    @if ($costing->loadingDayBonus > 0)
                        + {{ Format::money($costing->loadingDayBonus) }} <span class="text-ink-400">(zi încărcare)</span>
                    @endif
                </span>
                <span class="text-sm font-semibold text-copy">{{ Format::money($costing->driverSalary()) }}</span>
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Costurile cursei" meta="Tot ce consumă drumul.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field
                    model="consumption"
                    label="Consum"
                    type="number"
                    suffix="L/km"
                    step="0.01"
                    min="0"
                    :help="(is_numeric($this->consumption) ? '≈ '.Format::per100km((float) $this->consumption) : 'Litri pe kilometru, nu la sută.')
                        .($this->selectedVehicle ? ' · din '.$this->selectedVehicle->name : '')"
                    live
                    required
                />
                <x-dashboard.field model="fuel_price" label="Preț combustibil" type="number" :suffix="$lei.'/L'" step="0.5" min="0" live required />
                <x-dashboard.field
                    model="wear_per_km"
                    label="Uzură vehicul"
                    type="number"
                    :suffix="$lei.'/km'"
                    step="0.1"
                    min="0"
                    help="Tarif fix pentru toată flota, din Valori implicite. Editabil pe cursă."
                    live
                />
                <x-dashboard.field model="other_costs" label="Alte costuri" type="number" :suffix="$lei" step="50" min="0" live />
                <x-dashboard.field model="customs" label="Vamă" type="number" :suffix="$lei" step="50" min="0" live help="0 dacă ruta nu trece o vamă." />
                <x-dashboard.field model="vignette" label="Rovinietă" type="number" :suffix="$lei" step="50" min="0" live />
            </div>

            <div class="mt-4">
                <x-dashboard.field model="notes" label="Note" type="textarea" rows="3" placeholder="Marfă, termen de plată, observații." />
            </div>
        </x-dashboard.card>
    </div>

    {{-- The pinned column. `dash-aside` measures itself in `cqh` against <main>,
         which the dashboard layout makes a size container — so the Save row at
         its foot is always on screen, whatever the form beside it is doing. --}}
    <div class="dash-aside space-y-4">
        <div class="rounded-panel border border-forest bg-forest p-5 text-white">
            <p class="text-xs font-medium tracking-[0.12em] text-lime-soft uppercase">Profit final</p>

            <div class="mt-3 flex flex-wrap items-end gap-2">
                <p @class([
                    'text-4xl leading-none font-semibold tracking-tight',
                    'text-lime' => $profitable,
                    'text-red-300' => ! $profitable,
                ])>{{ $ready ? Format::money($costing->profit()) : '—' }}</p>
            </div>

            <p class="mt-2 text-xs text-white/70">
                @if ($ready)
                    Marjă {{ Format::percent($costing->margin()) }} · {{ Format::perKm($costing->profitPerKm()) }}
                @else
                    Completează distanța pentru un rezultat.
                @endif
            </p>

            <dl class="mt-5 space-y-2.5 border-t border-white/15 pt-4 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-white/70">Preț rută</dt>
                    <dd class="font-medium">{{ Format::money($costing->revenue()) }}</dd>
                </div>
                @if ($costing->hasReturnLoad())
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <dt class="text-white/50">din care retur</dt>
                        <dd class="text-white/70">{{ Format::money($costing->returnRevenueEur() * $costing->eurRate) }}</dd>
                    </div>
                @endif
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-white/70">Cost total</dt>
                    <dd class="font-medium">{{ Format::money($costing->totalCost()) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-white/70">Combustibil</dt>
                    <dd class="font-medium">{{ Format::litres($costing->fuelLitres()) }} · {{ Format::money($costing->fuelCost()) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-white/70">Distanță</dt>
                    <dd class="font-medium">{{ Format::km($costing->totalDistanceKm()) }}</dd>
                </div>
            </dl>
        </div>

        <x-dashboard.card title="Prag de rentabilitate">
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Pe cursă</dt>
                    <dd class="font-medium text-copy">{{ Format::money($costing->breakEven()) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Pe tonă</dt>
                    <dd class="font-medium text-copy">{{ $costing->breakEvenPerTonneEur() > 0 ? Format::eur($costing->breakEvenPerTonneEur()) : '—' }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs leading-relaxed text-muted">Sub aceste cifre cursa iese pe pierdere.</p>
        </x-dashboard.card>

        <x-dashboard.card title="Pe kilometru">
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Venit</dt>
                    <dd class="font-medium text-copy">{{ $ready ? Format::perKm($costing->revenuePerKm()) : '—' }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Cost</dt>
                    <dd class="font-medium text-copy">{{ $ready ? Format::perKm($costing->costPerKm()) : '—' }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2.5">
                    <dt class="text-muted">Profit</dt>
                    <dd @class(['font-semibold', 'text-copy' => $profitable, 'text-red-700' => ! $profitable])>{{ $ready ? Format::perKm($costing->profitPerKm()) : '—' }}</dd>
                </div>
            </dl>
        </x-dashboard.card>

        @if ($costing->breakdown() !== [])
            <x-dashboard.card title="Din ce e făcut costul">
                <ul class="space-y-3">
                    @foreach ($costing->breakdown() as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate text-copy">{{ $row['label'] }}</span>
                                <span class="shrink-0 font-medium text-copy">{{ Format::money($row['amount']) }}</span>
                            </div>
                            {{-- The bar is decoration over the figure beside it, so
                                 it carries no separate label for a screen reader. --}}
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-pill bg-line" aria-hidden="true">
                                <div class="h-full rounded-pill bg-forest" style="width: {{ round($row['share'], 2) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-dashboard.card>
        @endif

        {{-- Held at the foot of the column, opaque and bled over the scrollbar
             gutter, so the cards pass behind it cleanly. --}}
        <div class="dash-aside-actions flex items-center gap-2">
            <button
                type="button"
                wire:click="save"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90 disabled:opacity-60"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <x-dashboard.icon name="check" size="size-4" />
                {{ $editing ? 'Actualizează ruta' : 'Salvează ruta' }}
            </button>

            <button
                type="button"
                wire:click="resetForm"
                class="tap-target inline-flex items-center justify-center rounded-button border border-line bg-paper px-3 text-ink-500 transition-colors hover:bg-warm hover:text-forest"
                @php $resetLabel = $editing ? 'Revino la cifrele salvate' : __('dashboard.actions.reset'); @endphp
                title="{{ $resetLabel }}"
                aria-label="{{ $resetLabel }}"
            >
                <x-dashboard.icon name="reset" size="size-4" />
            </button>
        </div>
    </div>

    {{-- Leaving this page with a half-filled route in it costs the whole thing,
         so an in-app link asks first. The field ids are the ones resetForm()
         clears — the ones a user actually typed, as opposed to the parameters
         that arrived filled in.

         A new route only. On a saved one those boxes arrive filled, so the guard
         would read every visit as unsaved work and stop the user on the way out
         of a form they had only opened to look at. --}}
    @unless ($editing)
        <x-dashboard.unsaved-guard
            save="save"
            :fields="[
                'field-origin',
                'field-destination',
                'field-distance_km',
                'field-tonnes',
                'field-price_per_tonne',
                'field-notes',
            ]"
        />
    @endunless
</div>
