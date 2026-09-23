@php
    use App\Support\Format;
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-panel border border-line bg-lime/15 px-4 py-3 text-sm text-copy">{{ session('status') }}</div>
        @endif

        <x-dashboard.card title="Marfă și curs" meta="Cum se transformă prețul cotat în euro într-o încasare în {{ $currency }}.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field model="eur_rate" label="Curs EUR/{{ $currency }}" type="number" step="0.01" min="0" required />
                <x-dashboard.field model="currency" label="Monedă" placeholder="MDL" help="Simbolul afișat lângă fiecare sumă." required />
            </div>

            {{-- The one field here that does touch existing records. Every other
                 parameter is copied onto a route at save time; the symbol is not,
                 because a route carrying its own would make the totals on the
                 summary a sum of mixed currencies. --}}
            <p class="mt-4 flex items-start gap-2 rounded-button border border-line bg-warm px-4 py-3 text-xs leading-relaxed text-muted">
                <x-dashboard.icon name="warning" size="size-4" class="mt-px shrink-0 text-ink-400" />
                <span>
                    Moneda este doar simbolul afișat, nu o conversie. Dacă o schimbi,
                    sumele deja salvate rămân aceleași cifre, dar vor fi afișate cu
                    noul simbol.
                </span>
            </p>
        </x-dashboard.card>

        <x-dashboard.card title="Drum" meta="Ce consumă cursa, indiferent de vehicul.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field
                    model="consumption"
                    label="Consum"
                    type="number"
                    suffix="L/km"
                    step="0.01"
                    min="0"
                    :help="is_numeric($consumption) ? '≈ '.Format::per100km((float) $consumption) : 'Litri pe kilometru, nu la sută.'"
                    live
                    required
                />
                <x-dashboard.field model="fuel_price" label="Preț combustibil" type="number" :suffix="$currency.'/L'" step="0.5" min="0" required />
                <x-dashboard.field
                    model="wear_per_km"
                    label="Uzură vehicul"
                    type="number"
                    :suffix="$currency.'/km'"
                    step="0.1"
                    min="0"
                    help="Tarif fix, aplicat pe toate cursele. Vezi în Vehicule cât costă în realitate fiecare camion."
                    required
                />
                <x-dashboard.field model="gps_monthly" label="GPS" type="number" :suffix="$currency.'/lună'" step="50" min="0" help="Abonamentul cu care pornește un vehicul nou." required />
                <x-dashboard.field model="customs" label="Vamă" type="number" :suffix="$currency" step="50" min="0" required />
                <x-dashboard.field model="vignette" label="Rovinietă" type="number" :suffix="$currency" step="50" min="0" required />
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Salariu șofer" meta="Prima zi la tariful ei, fiecare zi următoare la cel adițional.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field model="driver_first_day" label="Prima zi" type="number" :suffix="$currency" step="50" min="0" live required />
                <x-dashboard.field model="driver_extra_day" label="Zi adițională" type="number" :suffix="$currency" step="50" min="0" live required />
                <x-dashboard.field
                    model="loading_day_bonus"
                    label="Zi de încărcare"
                    type="number"
                    :suffix="$currency"
                    step="100"
                    min="0"
                    help="Peste tariful zilei, când cursa ia marfă de retur."
                    required
                />
            </div>

            @php
                $first = is_numeric($driver_first_day) ? (float) $driver_first_day : 0.0;
                $extra = is_numeric($driver_extra_day) ? (float) $driver_extra_day : 0.0;
            @endphp

            {{-- A short table of what the two rates come to, because the rule is
                 easier to check against three examples than to read. --}}
            <dl class="mt-4 divide-y divide-line rounded-button border border-line bg-warm text-sm">
                @foreach ([1, 3, 5] as $days)
                    <div class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                        <dt class="text-muted">{{ $days }} {{ $days === 1 ? 'zi' : 'zile' }}</dt>
                        <dd class="font-medium text-copy">{{ Format::money($first + ($days - 1) * $extra) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-dashboard.card>
    </div>

    <div class="dash-aside space-y-4">
        <x-dashboard.card title="Ce se schimbă și ce nu">
            <p class="text-sm leading-relaxed text-muted">
                Valorile de aici sunt doar un punct de plecare: fiecare rămâne editabilă
                în calculator, pe fiecare cursă în parte.
            </p>
            <p class="mt-3 text-sm leading-relaxed text-muted">
                Rutele deja salvate nu se schimbă. Fiecare și-a reținut cifrele cu care a
                fost calculată — inclusiv cursul valutar — așa că o marjă cotată acum șase
                luni rămâne cea cotată atunci.
            </p>
            <p class="mt-3 text-sm leading-relaxed text-muted">
                Uzura de mai sus se folosește doar când cursa nu are un vehicul ales.
                Când are, calculatorul ia cheltuielile anuale ale camionului respectiv.
            </p>
        </x-dashboard.card>

        <div class="dash-aside-actions">
            <button
                type="button"
                wire:click="save"
                class="inline-flex w-full items-center justify-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90 disabled:opacity-60"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <x-dashboard.icon name="check" size="size-4" />
                {{ __('dashboard.actions.save') }}
            </button>
        </div>
    </div>
</div>
