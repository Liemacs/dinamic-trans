@props([
    // App\Support\PeriodReport
    'report',
    // The truck the report is about, or null for the whole fleet.
    'vehicle' => null,
])

@php
    use App\Models\Setting;
    use App\Support\Format;

    $lei = Setting::currency();

    $rows = $report->rows();
    $totals = $report->totals();
    $bottom = $report->bottomLine();

    $from = $report->from();
    $to = $report->to();

    $date = static fn ($moment): string => $moment->format('d.m.Y');

    // With one truck named in the header, a column repeating it on every line is
    // a column of the same word.
    $showVehicle = $vehicle === null;
@endphp

{{-- The sheet, on screen and on paper alike: black on white, hairline borders,
     the totals directly under the lines they come from. Modelled on the paper
     darea de seamă, minus the columns this app has no figures for. --}}
<div {{ $attributes->class(['bg-white text-copy']) }}>
    <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2 border-b-2 border-copy pb-2">
        <div>
            <h2 class="text-base font-bold tracking-wide uppercase">Darea de seamă</h2>
            <p class="mt-1 text-xs">
                Perioada <span class="font-semibold">{{ $date($from) }} – {{ $date($to) }}</span>
                · Camion: <span class="font-semibold">{{ $vehicle?->label() ?? 'toată flota' }}</span>
                · {{ $totals['routes'] }} {{ $totals['routes'] === 1 ? 'rută' : 'rute' }}
            </p>
        </div>

        <p class="text-[10px] text-muted">
            Sumele în {{ $lei }}, rotunjite la leu · generat {{ $date(now()) }}
        </p>
    </header>

    @if ($rows === [])
        <p class="py-8 text-center text-sm text-muted">
            Nicio rută salvată în perioada aceasta.
        </p>
    @else
        <table class="mt-3 w-full border-collapse text-left text-[11px]">
            <thead>
                <tr class="border-b border-copy align-bottom">
                    <th class="py-1.5 pr-2 font-semibold">Data</th>
                    <th class="py-1.5 pr-2 font-semibold">Sarcina</th>
                    @if ($showVehicle)
                        <th class="py-1.5 pr-2 font-semibold">Camion</th>
                    @endif
                    <th class="py-1.5 pr-2 text-right font-semibold">Tone dus</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Tone retur</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Distanță</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Motorină</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Venit</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Cheltuit</th>
                    <th class="py-1.5 pr-2 text-right font-semibold">Profit</th>
                    <th class="py-1.5 text-right font-semibold">Marjă</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr class="break-inside-avoid border-b border-line">
                        <td class="py-1.5 pr-2 whitespace-nowrap tabular-nums">{{ $date($row['date']) }}</td>
                        <td class="py-1.5 pr-2">{{ $row['label'] }}</td>
                        @if ($showVehicle)
                            <td class="py-1.5 pr-2">{{ $row['vehicle'] }}</td>
                        @endif
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($row['tonnes'], 1) }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ $row['return_tonnes'] > 0.0 ? Format::number($row['return_tonnes'], 1) : '—' }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($row['distance'], 0) }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($row['litres'], 0) }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($row['revenue'], 0) }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($row['cost'], 0) }}</td>
                        <td @class(['py-1.5 pr-2 text-right font-medium tabular-nums', 'text-red-700' => $row['profit'] < 0.0])>{{ Format::number($row['profit'], 0) }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ Format::percent($row['margin'], 0) }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr class="border-t-2 border-copy font-semibold">
                    <td class="py-1.5 pr-2" colspan="{{ $showVehicle ? 3 : 2 }}">Total</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['tonnes'], 1) }}</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['return_tonnes'], 1) }}</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['distance'], 0) }}</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['litres'], 0) }}</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['revenue'], 0) }}</td>
                    <td class="py-1.5 pr-2 text-right tabular-nums">{{ Format::number($totals['cost'], 0) }}</td>
                    <td @class(['py-1.5 pr-2 text-right tabular-nums', 'text-red-700' => $totals['profit'] < 0.0])>{{ Format::number($totals['profit'], 0) }}</td>
                    <td class="py-1.5 text-right tabular-nums">{{ Format::percent($totals['margin'], 0) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Kept whole on one page: a result split across a page break is a result
         nobody trusts. --}}
    <div class="mt-5 grid break-inside-avoid gap-5 sm:grid-cols-2">
        <div>
            <h3 class="text-xs font-bold tracking-wide uppercase">Rezultatul perioadei</h3>

            <dl class="mt-2 space-y-1.5 text-[11px]">
                <div class="flex items-baseline justify-between gap-3">
                    <dt>Venit din rute</dt>
                    <dd class="font-medium tabular-nums">{{ Format::number($bottom['revenue'], 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt>Costuri de drum <span class="text-muted">— combustibil, salariu, vamă, rovinietă, alte</span></dt>
                    <dd class="tabular-nums">−{{ Format::number($bottom['road_cost'], 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-1.5">
                    <dt class="font-medium">Contribuție</dt>
                    <dd class="font-medium tabular-nums">{{ Format::number($bottom['contribution'], 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt>
                        Cheltuieli camioane
                        <span class="text-muted">— {{ $report->days() }} {{ $report->days() === 1 ? 'zi' : 'zile' }} din {{ Format::number($bottom['fixed_annual'], 0) }}/an</span>
                    </dt>
                    <dd class="tabular-nums">−{{ Format::number($bottom['fixed'], 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t-2 border-copy pt-1.5 text-sm">
                    <dt class="font-bold">Rezultat</dt>
                    <dd @class(['font-bold tabular-nums', 'text-red-700' => $bottom['result'] < 0.0])>{{ Format::money($bottom['result'], 0) }}</dd>
                </div>
            </dl>
        </div>

        <div class="text-[10px] leading-relaxed text-muted">
            <h3 class="text-xs font-bold tracking-wide text-copy uppercase">Note</h3>

            <ul class="mt-2 space-y-1.5">
                <li>
                    <span class="font-medium text-copy">Uzura nu se scade de două ori.</span>
                    Profitul fiecărei rute conține deja uzura pe kilometru — un tarif fix, cu care
                    cursele contribuie la cheltuielile camioanelor. De aceea „costuri de drum" pornește
                    fără ea, iar cheltuielile reale ale camioanelor intră o singură dată, mai jos, cu
                    factura pe an.
                </li>
                <li>
                    Prin kilometrii din perioadă s-au recuperat
                    <span class="font-medium text-copy">{{ Format::number($bottom['wear'], 0) }} {{ $lei }}</span>
                    din cheltuielile camioanelor
                    @if ($bottom['fixed'] > 0.0)
                        ({{ Format::percent($bottom['coverage'], 0) }} din cât s-a cuvenit pe perioadă).
                    @else
                        .
                    @endif
                </li>
                <li>
                    Cheltuielile camioanelor sunt facturi pe an, împărțite proporțional pe zilele perioadei.
                    @if ($vehicle === null)
                        Intră camioanele active din flotă ({{ $bottom['vehicles'] }}).
                    @endif
                </li>
                <li>
                    O rută intră în perioadă după data la care a fost salvată — este singura dată pe care o
                    are. Cifrele ei sunt cele înghețate la salvare, nu recalculate acum.
                </li>
            </ul>
        </div>
    </div>
</div>
