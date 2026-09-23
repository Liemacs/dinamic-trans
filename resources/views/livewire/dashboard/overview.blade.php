@php
    use App\Models\Setting;
    use App\Support\FleetSummary;
    use App\Support\Format;

    $lei = Setting::currency();

    $totals = $this->totals;
    $series = $this->series;
    $fleet = $this->perVehicle;
    $annual = $this->annual;

    $window = $this->window;

    // The twelve months the "Rezultat anual" card reports on, as the report
    // screen wants them: whole months, oldest first.
    $annualFrom = now()->startOfMonth()->subMonths(11)->toDateString();
    $annualTo = now()->endOfMonth()->toDateString();
    $windowLabel = $window === 'all' ? 'tot istoricul' : 'ultimele '.FleetSummary::PERIODS[$window];

    // The axis and the tooltips carry thousands of lei; four digits per label
    // would be wider than the columns they sit under.
    $short = static fn (float $value): string => abs($value) >= 1000
        ? Format::number($value / 1000, abs($value) >= 10000 ? 0 : 1).'k'
        : Format::number($value, 0);
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard.stat
            label="Profit total"
            :value="Format::money($totals['profit'])"
            icon="banknotes"
            tone="accent"
            :hint="$totals['count'] > 0 ? Format::perKm($totals['profit_per_km']).' pe toată flota' : 'Încă nicio rută salvată'"
        />
        <x-dashboard.stat
            label="Venit total"
            :value="Format::money($totals['revenue'])"
            icon="trending-up"
        />
        <x-dashboard.stat
            label="Marjă medie"
            :value="Format::percent($totals['margin'])"
            icon="calculator"
            hint="Ponderată după venit."
        />
        <x-dashboard.stat
            label="Rute calculate"
            :value="(string) $totals['count']"
            icon="route"
            :hint="Format::number($totals['distance'], 0).' km în total'"
        />
    </div>

    {{-- Nothing to plot before the first route is saved: the empty state under
         „Ultimele rute" says it once, and three empty charts would say it again. --}}
    @if ($totals['count'] > 0)
        @php
            $granularity = $series['granularity'];
            $scale = $series['max'] > 0.0 ? $series['max'] : 1.0;
            $hasLoss = collect($series['buckets'])->contains(fn (array $bucket): bool => $bucket['profit'] < 0.0);

            $chartTitle = match ($granularity) {
                'year' => 'Venit anual',
                'quarter' => 'Venit trimestrial',
                default => 'Venit lunar',
            };
            $bucketNoun = match ($granularity) {
                'year' => 'an',
                'quarter' => 'trimestru',
                default => 'lună',
            };
        @endphp

        {{-- One switch above both charts rather than inside one of them: it
             governs the pair, and a control in a card header reads as belonging
             to that card alone. Each card still names the window it draws, so
             the two can never be read apart. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-copy">
                    Cum a mers flota <span class="font-normal text-muted">· {{ $windowLabel }}</span>
                </p>
                <p class="mt-0.5 text-xs text-muted">O rută intră în perioada în care a fost salvată.</p>
            </div>

            <div class="inline-flex items-center rounded-button border border-line bg-warm p-0.5" role="group" aria-label="Perioadă">
                @foreach (FleetSummary::PERIODS as $key => $label)
                    {{-- Cast: PHP stores '6' and '12' as integer keys, and the
                         period they are compared against is a string. --}}
                    @php $current = (string) $key === $window; @endphp

                    <button
                        type="button"
                        wire:click="setPeriod('{{ $key }}')"
                        @class([
                            'rounded-[0.6rem] px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                            'bg-forest text-white' => $current,
                            'text-ink-500 hover:text-forest' => ! $current,
                        ])
                        @if ($current) aria-current="true" @endif
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-dashboard.card
            :title="$chartTitle"
            :meta="'Venitul pe '.$bucketNoun.', cu profitul din el, în '.$lei.'.'"
        >
            <div class="flex gap-3" aria-hidden="true">
                <div class="flex h-48 shrink-0 flex-col justify-between text-right text-[10px] leading-none tabular-nums text-muted sm:h-56">
                    <span>{{ $short($series['max']) }}</span>
                    <span>{{ $short($series['max'] / 2) }}</span>
                    <span>0</span>
                </div>

                {{-- Wider than the card on a phone, so it opens on the most
                     recent columns and scrolls back into the past. --}}
                <div
                    class="min-w-0 flex-1 overflow-x-auto pb-1"
                    x-data
                    x-init="$nextTick(() => $el.scrollLeft = $el.scrollWidth)"
                >
                    <div class="relative h-48 w-max min-w-full sm:h-56">
                        <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-line"></div>
                        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-line"></div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 border-t border-line"></div>

                        {{-- Positioned, so the columns paint over the gridlines
                             rather than under their dashes. --}}
                        <div class="relative flex h-full items-end gap-1.5">
                            @foreach ($series['buckets'] as $bucket)
                                @php
                                    // The column stands as tall as whichever is
                                    // larger. Below the split is what the run
                                    // cost and the revenue covered; above it is
                                    // either the profit or the hole left in it.
                                    $covered = min($bucket['revenue'], $bucket['cost']);
                                    $gap = abs($bucket['profit']);
                                    $loss = $bucket['profit'] < 0.0;
                                @endphp

                                {{-- Capped as well as floored: six buckets across a
                                     desktop card would otherwise be six slabs. --}}
                                <div
                                    class="flex h-full min-w-7 max-w-20 flex-1 flex-col justify-end"
                                    title="{{ $bucket['title'] }} · {{ $bucket['count'] }} {{ $bucket['count'] === 1 ? 'rută' : 'rute' }} · venit {{ Format::money($bucket['revenue'], 0) }} · profit {{ Format::money($bucket['profit'], 0) }}"
                                >
                                    @if ($bucket['count'] === 0)
                                        <div class="h-0.5 rounded-t-sm bg-line"></div>
                                    @else
                                        @if ($gap > 0.0)
                                            <div
                                                @class(['rounded-t-sm', 'bg-forest' => ! $loss, 'bg-red-600/70' => $loss])
                                                style="height: {{ round($gap / $scale * 100, 2) }}%"
                                            ></div>
                                        @endif
                                        <div
                                            @class(['bg-ink-200', 'rounded-t-sm' => $gap <= 0.0])
                                            style="height: {{ round($covered / $scale * 100, 2) }}%"
                                        ></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-2 flex w-max min-w-full gap-1.5">
                        @foreach ($series['buckets'] as $bucket)
                            <div class="min-w-7 max-w-20 flex-1 text-center text-[10px] leading-tight text-muted">
                                <span class="block truncate">{{ $bucket['label'] }}</span>
                                <span class="block truncate text-ink-400">{{ $bucket['year'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- The columns carry no text, so the same figures are laid out here
                 for anything that reads the page instead of looking at it. --}}
            <table class="sr-only">
                <caption>{{ $chartTitle }}, {{ $windowLabel }}</caption>
                <thead>
                    <tr><th>Perioadă</th><th>Rute</th><th>Venit</th><th>Cost</th><th>Profit</th></tr>
                </thead>
                <tbody>
                    @foreach ($series['buckets'] as $bucket)
                        <tr>
                            <td>{{ $bucket['title'] }}</td>
                            <td>{{ $bucket['count'] }}</td>
                            <td>{{ Format::money($bucket['revenue']) }}</td>
                            <td>{{ Format::money($bucket['cost']) }}</td>
                            <td>{{ Format::money($bucket['profit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-line pt-3 text-xs text-muted">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-xs bg-forest"></span>Profit</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-xs bg-ink-200"></span>Costuri</span>
                    @if ($hasLoss)
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-xs bg-red-600/70"></span>Pierdere</span>
                    @endif
                </div>
                <p class="tabular-nums">
                    {{ $series['count'] }} {{ $series['count'] === 1 ? 'rută' : 'rute' }} ·
                    venit <span class="font-medium text-copy">{{ Format::money($series['revenue'], 0) }}</span> ·
                    profit <span @class(['font-medium', 'text-copy' => $series['profit'] >= 0.0, 'text-red-700' => $series['profit'] < 0.0])>{{ Format::money($series['profit'], 0) }}</span>
                </p>
            </div>
        </x-dashboard.card>

        <div class="grid gap-6 xl:grid-cols-2">
            @php $fleetMax = $fleet === [] ? 0.0 : (float) max(array_column($fleet, 'revenue')); @endphp

            <x-dashboard.card title="Venituri per mașină" :meta="'Ce a adus fiecare camion în '.$windowLabel.'.'">
                @if ($fleet === [])
                    <x-dashboard.empty-state
                        icon="truck"
                        title="Nicio mașină de arătat"
                        description="Adaugă un camion în flotă și alege-l în calculator — de acolo încolo fiecare cursă se pune în dreptul lui."
                    />
                @else
                    <ul class="space-y-4">
                        @foreach ($fleet as $row)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate">
                                        <span class="font-medium text-copy">{{ $row['label'] }}</span>
                                        @if ($row['note'])
                                            <span class="text-xs text-muted">· {{ $row['note'] }}</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 font-medium tabular-nums text-copy">{{ Format::money($row['revenue'], 0) }}</span>
                                </div>

                                {{-- Bar length is the truck's share of the busiest
                                     one; the dark head inside it is the profit
                                     left after that revenue paid for the run. --}}
                                <div class="mt-1.5 h-2 overflow-hidden rounded-pill bg-warm" aria-hidden="true">
                                    <div class="flex h-full rounded-pill bg-ink-300" style="width: {{ $fleetMax > 0.0 ? round($row['revenue'] / $fleetMax * 100, 2) : 0 }}%">
                                        @if ($row['profit'] > 0.0 && $row['revenue'] > 0.0)
                                            <div class="h-full rounded-pill bg-forest" style="width: {{ round(min($row['profit'] / $row['revenue'], 1) * 100, 2) }}%"></div>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-xs text-muted tabular-nums">
                                    @if ($row['routes'] === 0)
                                        <span>fără curse în {{ $windowLabel }}</span>
                                        @if ($row['fixed'] > 0.0)
                                            <span>·</span>
                                            <span>{{ Format::money($row['fixed'], 0) }} cheltuieli pe an</span>
                                        @endif
                                    @else
                                        <span>{{ $row['routes'] }} {{ $row['routes'] === 1 ? 'rută' : 'rute' }}</span>
                                        <span>{{ Format::km($row['distance']) }}</span>
                                        <span>
                                            profit
                                            <span @class(['font-medium', 'text-copy' => $row['profit'] >= 0.0, 'text-red-700' => $row['profit'] < 0.0])>{{ Format::money($row['profit'], 0) }}</span>
                                            ({{ Format::percent($row['margin']) }})
                                        </span>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.card>

            <x-dashboard.card title="Rezultat anual" meta="Ultimele 12 luni, după cheltuielile camioanelor.">
                <div class="rounded-panel border border-forest bg-forest p-4 sm:p-5">
                    <p class="text-xs font-medium tracking-[0.08em] text-lime-soft uppercase">Cu cât ești în plus într-un an</p>
                    <p @class(['mt-2 text-2xl font-semibold tracking-tight sm:text-3xl', 'text-lime' => $annual['result'] >= 0.0, 'text-red-300' => $annual['result'] < 0.0])>
                        {{ Format::money($annual['result'], 0) }}
                    </p>
                    <p class="mt-1 text-xs text-white/60">
                        @if ($annual['routes'] === 0)
                            Nicio rută în ultimele 12 luni — rămân doar cheltuielile camioanelor.
                        @elseif ($annual['partial'])
                            {{ $annual['months'] === 1 ? 'Dintr-o lună' : 'Din '.$annual['months'].' luni' }} de rute salvate, contra unui an întreg de cheltuieli.
                        @else
                            Din {{ $annual['routes'] }} {{ $annual['routes'] === 1 ? 'rută' : 'rute' }} salvate în ultimele 12 luni.
                        @endif
                    </p>
                </div>

                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">Venit din rute</dt>
                        <dd class="font-medium tabular-nums text-copy">{{ Format::money($annual['revenue'], 0) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">Costuri de drum</dt>
                        <dd class="tabular-nums text-copy">−{{ Format::money($annual['road_cost'], 0) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2.5">
                        <dt class="text-copy">Contribuție</dt>
                        <dd class="font-medium tabular-nums text-copy">{{ Format::money($annual['contribution'], 0) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-muted">Cheltuieli camioane (an)</dt>
                        <dd class="tabular-nums text-copy">−{{ Format::money($annual['fixed'], 0) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2.5">
                        <dt class="font-medium text-copy">Rezultat</dt>
                        <dd @class(['font-semibold tabular-nums', 'text-copy' => $annual['result'] >= 0.0, 'text-red-700' => $annual['result'] < 0.0])>{{ Format::money($annual['result'], 0) }}</dd>
                    </div>
                </dl>

                @if ($annual['fixed'] > 0.0)
                    <div class="mt-4 border-t border-line pt-3">
                        <div class="flex items-baseline justify-between gap-3 text-xs">
                            <span class="text-muted">Cheltuieli acoperite de uzura pe km</span>
                            <span class="font-medium tabular-nums text-copy">{{ Format::percent($annual['coverage'], 0) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-pill bg-line" aria-hidden="true">
                            <div class="h-full rounded-pill bg-forest" style="width: {{ round(min($annual['coverage'], 100), 2) }}%"></div>
                        </div>
                        <p class="mt-1.5 text-xs text-muted">
                            {{ Format::money($annual['wear'], 0) }} încasați prin uzura de pe kilometri, din {{ Format::money($annual['fixed'], 0) }} de facturi pe an.
                        </p>
                    </div>
                @endif

                {{-- The report opens on exactly the twelve months this card is
                     showing, so the sheet that prints is the figure above it. --}}
                <a
                    href="{{ route('dashboard.report', ['from' => $annualFrom, 'to' => $annualTo]) }}"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-button border border-line bg-paper px-4 py-2.5 text-sm font-medium text-copy transition-colors hover:bg-warm hover:text-forest"
                >
                    <x-dashboard.icon name="document" size="size-4" />
                    Raport anual
                </a>

                <p class="mt-4 text-xs text-muted">
                    @if ($annual['vehicles'] === 0)
                        Niciun camion activ în flotă, deci nu e nimic de scăzut aici.
                        <a href="{{ route('dashboard.fleet.vehicles') }}" class="font-medium text-copy underline underline-offset-2">Adaugă cheltuielile camioanelor</a>
                        ca rezultatul să fie întreg.
                    @else
                        Uzura pe km nu se scade de două ori: ea este tariful fix cu care cursele
                        contribuie la cheltuielile camioanelor, iar acelea intră aici o singură dată,
                        cu factura pe an.
                        @if ($annual['partial'] && $annual['run_rate'] !== null)
                            În ritmul {{ $annual['months'] === 1 ? 'ultimei luni' : 'ultimelor '.$annual['months'].' luni' }},
                            un an întreg ar însemna ≈ {{ Format::money($annual['run_rate'], 0) }}.
                        @endif
                    @endif
                </p>
            </x-dashboard.card>
        </div>
    @endif

    <x-dashboard.card title="Ultimele rute" meta="Cele mai recente șase calcule." :padded="false">
        <x-slot:header>
            <a href="{{ route('dashboard.calculator') }}"
               class="inline-flex items-center gap-1.5 rounded-button bg-forest px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-forest/90">
                <x-dashboard.icon name="plus" size="size-4" />
                Rută nouă
            </a>
        </x-slot:header>

        @if ($this->recent->isEmpty())
            <x-dashboard.empty-state
                icon="route"
                title="Încă nicio rută"
                description="Calculează prima cursă și salveaz-o — de acolo încolo sumarul de mai sus se completează singur."
            >
                <a href="{{ route('dashboard.calculator') }}"
                   class="inline-flex items-center gap-1.5 rounded-button bg-forest px-3.5 py-2 text-sm font-medium text-white transition-colors hover:bg-forest/90">
                    <x-dashboard.icon name="plus" size="size-4" />
                    Deschide calculatorul
                </a>
            </x-dashboard.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th class="px-4 py-3 sm:pl-5">Rută</th>
                            <th class="px-4 py-3 text-right">Distanță</th>
                            <th class="px-4 py-3 text-right">Venit</th>
                            <th class="px-4 py-3 text-right">Profit</th>
                            <th class="px-4 py-3 text-right sm:pr-5">Marjă</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->recent as $route)
                            @php $costing = $route->costing(); @endphp
                            <tr class="bg-paper transition-colors hover:bg-warm/60">
                                <td class="px-4 py-3 sm:pl-5">
                                    <span class="block font-medium text-copy">{{ $route->label() }}</span>
                                    <span class="block text-xs text-muted">{{ $route->vehicle?->label() ?? 'Fără vehicul' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::km($costing->totalDistanceKm()) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($costing->revenue()) }}</td>
                                <td @class(['px-4 py-3 text-right font-medium tabular-nums', 'text-copy' => $costing->profit() >= 0, 'text-red-700' => $costing->profit() < 0])>
                                    {{ Format::money($costing->profit()) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-muted sm:pr-5">{{ Format::percent($costing->margin()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-dashboard.card>
</div>
