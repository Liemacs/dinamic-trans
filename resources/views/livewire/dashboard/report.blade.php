@php
    use App\Support\PeriodReport;

    $report = $this->report;
    $active = $this->activePreset;
@endphp

<div class="space-y-6">
    <x-dashboard.card title="Perioada raportului" meta="Alege intervalul și camionul; foaia de mai jos se schimbă odată cu ele.">
        <div class="flex flex-wrap gap-2">
            @foreach (PeriodReport::PRESETS as $key => $label)
                <button
                    type="button"
                    wire:click="preset('{{ $key }}')"
                    @class([
                        'rounded-button border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                        'border-forest bg-forest text-white' => $active === $key,
                        'border-line bg-paper text-ink-500 hover:border-forest hover:text-forest' => $active !== $key,
                    ])
                    @if ($active === $key) aria-current="true" @endif
                >{{ $label }}</button>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard.field model="from" label="De la" type="date" live />
            <x-dashboard.field model="to" label="Până la" type="date" live />

            {{-- No help text on this one: it would push the select below the
                 two date fields beside it. The note goes under the row. --}}
            <x-dashboard.field
                model="vehicle_id"
                label="Camion"
                type="select"
                live
                class="lg:col-span-2"
            >
                <option value="">Toată flota</option>
                @foreach ($this->vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">{{ $vehicle->label() }}{{ $vehicle->active ? '' : ' (inactiv)' }}</option>
                @endforeach
            </x-dashboard.field>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
            <p class="min-w-0 text-xs text-muted">
                {{ $this->vehicle
                    ? 'Doar cursele lui '.$this->vehicle->name.', și doar cheltuielile lui anuale.'
                    : 'Toate cursele, și cheltuielile camioanelor active din flotă.' }}
                @if ($active === null)
                    · Interval ales de tine: {{ $report->days() }} {{ $report->days() === 1 ? 'zi' : 'zile' }}.
                @endif
            </p>

            <div class="flex flex-wrap items-center gap-2">
                {{-- A plain link: the browser downloads the file and the screen
                     behind it stays exactly as it was set up. --}}
                <a
                    href="{{ $this->excelUrl }}"
                    class="inline-flex items-center gap-2 rounded-button border border-line bg-paper px-3.5 py-2 text-sm font-medium text-copy transition-colors hover:bg-warm hover:text-forest"
                >
                    <x-dashboard.icon name="download" size="size-4" />
                    Excel
                </a>

                {{-- A new tab rather than this one: the sheet opens the print
                     dialog on arrival, and coming back should be a tab away, not
                     a reload of the screen you set up. --}}
                <a
                    href="{{ $this->printUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-button bg-forest px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-forest/90"
                >
                    <x-dashboard.icon name="printer" size="size-4" />
                    Tipărește / PDF
                </a>
            </div>
        </div>
    </x-dashboard.card>

    <x-dashboard.card title="Foaia" meta="Exact ce se tipărește." class="overflow-x-auto">
        <x-report.sheet :report="$report" :vehicle="$this->vehicle" class="min-w-[46rem]" />
    </x-dashboard.card>
</div>
