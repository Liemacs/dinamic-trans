@php
    use App\Models\Setting;
    use App\Support\Format;

    $lei = Setting::currency();
    $draft = $this->draft;
@endphp

<div class="space-y-6">
    {{-- The model names already on the fleet. A fleet runs several of the same
         lorry, so repeating a name is normal — repeating it differently is not,
         and Vehicle::canonicalName() folds what is typed onto the spelling
         already here. --}}
    <datalist id="nume-camioane">
        @foreach ($this->names as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-panel border border-line bg-lime/15 px-4 py-3 text-sm text-copy">{{ session('status') }}</div>
        @endif

        <x-dashboard.card
            :padded="false"
            :title="$this->vehicles->count().' '.($this->vehicles->count() === 1 ? 'vehicul' : 'vehicule')"
            meta="Ce costă fiecare camion într-un an, pe categorii."
        >
            @if ($this->vehicles->isEmpty())
                <x-dashboard.empty-state
                    icon="truck"
                    title="Încă niciun vehicul"
                    description="Adaugă un camion cu cheltuielile lui anuale și calculatorul îți completează singur consumul și uzura de fiecare dată când îl alegi."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="border-b border-line text-xs font-semibold tracking-wide text-muted uppercase">
                            <tr>
                                <th class="px-4 py-3 sm:pl-5">Vehicul</th>
                                <th class="px-4 py-3 text-right">Consum</th>
                                <th class="px-4 py-3 text-right">Asigurare</th>
                                <th class="px-4 py-3 text-right">Service</th>
                                <th class="px-4 py-3 text-right">Anvelope</th>
                                <th class="px-4 py-3 text-right">Alte</th>
                                <th class="px-4 py-3 text-right">GPS</th>
                                <th class="px-4 py-3 text-right">Total anual</th>
                                <th class="px-4 py-3 text-right sm:pr-5"><span class="sr-only">Acțiuni</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($this->vehicles as $vehicle)
                                <tr
                                    wire:key="vehicle-{{ $vehicle->id }}"
                                    @class([
                                        'transition-colors',
                                        'bg-lime-soft/50' => $editing === $vehicle->id,
                                        'bg-paper hover:bg-warm/60' => $editing !== $vehicle->id,
                                    ])
                                >
                                    <td class="px-4 py-3 sm:pl-5">
                                        <span class="block font-medium text-copy">{{ $vehicle->name }}</span>
                                        <span class="block text-xs text-muted">
                                            {{ $vehicle->plate ?: 'fără număr' }}
                                            @unless ($vehicle->active) · inactiv @endunless
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">
                                        {{ Format::number($vehicle->consumption, 2) }} L/km
                                        <span class="block text-xs text-muted">{{ Format::per100km($vehicle->consumption) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($vehicle->insurance_annual, 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($vehicle->service_annual, 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($vehicle->tyres_annual, 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">{{ Format::money($vehicle->other_annual, 0) }}</td>
                                    {{-- The GPS is a subscription, so the monthly
                                         figure leads and the year it adds up to
                                         sits under it. --}}
                                    <td class="px-4 py-3 text-right tabular-nums text-copy">
                                        {{ Format::money($vehicle->gps_monthly, 0) }}<span class="text-muted">/lună</span>
                                        <span class="block text-xs text-muted">{{ Format::money($vehicle->gpsAnnual(), 0) }}/an</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-copy">{{ Format::money($vehicle->annualTotal(), 0) }}</td>
                                    <td class="px-4 py-3 text-right sm:pr-5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                wire:click="edit({{ $vehicle->id }})"
                                                class="rounded-button border border-line px-2.5 py-1.5 text-xs font-medium text-copy transition-colors hover:bg-warm"
                                            >Editează</button>

                                            <x-dashboard.confirm-delete
                                                :message="'Ștergi „'.$vehicle->label().'”? Rutele deja salvate își păstrează cifrele.'"
                                                :wire="'delete('.$vehicle->id.')'"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        @if ($this->vehicles->count() > 1)
                            <tfoot class="border-t border-line bg-warm/60 text-sm">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-copy sm:pl-5" colspan="7">Toată flota</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-copy">
                                        {{ Format::money($this->vehicles->sum(fn ($v) => $v->annualTotal()), 0) }}
                                    </td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </x-dashboard.card>
    </div>

    {{-- Under the table rather than beside it: at ten columns of annual figures
         the table needs the full width, and it is the context you want while
         typing an insurance premium anyway. --}}
    <div id="vehicul-form" class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <x-dashboard.card :title="$editing ? 'Editează vehiculul' : 'Vehicul nou'">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field model="name" label="Nume" placeholder="Volvo FH 460" list="nume-camioane" required />
                <x-dashboard.field model="plate" label="Număr" placeholder="CVB 407" />
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
            </div>
        </x-dashboard.card>

        <x-dashboard.card title="Cheltuieli anuale" meta="Ce costă camionul într-un an, pe categorii.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-dashboard.field model="insurance_annual" label="Asigurare" type="number" :suffix="$lei.'/an'" step="100" min="0" live />
                <x-dashboard.field model="service_annual" label="Service" type="number" :suffix="$lei.'/an'" step="100" min="0" live />
                <x-dashboard.field model="tyres_annual" label="Anvelope" type="number" :suffix="$lei.'/an'" step="100" min="0" live />
                <x-dashboard.field model="other_annual" label="Alte cheltuieli" type="number" :suffix="$lei.'/an'" step="100" min="0" help="Amortizare, taxe, revizii tehnice." live />

                {{-- The GPS is the one line billed monthly, so it is entered the
                     way the invoice arrives and annualised beside it. --}}
                <x-dashboard.field model="gps_monthly" label="GPS" type="number" :suffix="$lei.'/lună'" step="50" min="0" help="Abonament — se înmulțește cu 12 în total." live />
            </div>

            <dl class="mt-5 space-y-2.5 border-t border-line pt-4 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">GPS pe an</dt>
                    <dd class="font-medium text-copy">{{ Format::money($draft->gpsAnnual(), 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Total anual</dt>
                    <dd class="font-semibold text-copy">{{ Format::money($draft->annualTotal(), 0) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-muted">Pe lună</dt>
                    <dd class="font-medium text-copy">{{ Format::money($draft->monthlyTotal(), 0) }}</dd>
                </div>
            </dl>


            <label class="mt-5 flex items-center gap-2.5 text-sm text-copy">
                {{-- `accent-forest` rather than `text-forest`: without the forms
                     plugin a checkbox is still the browser's own control, and
                     only accent-color reaches it. --}}
                <input type="checkbox" wire:model="active" class="size-4 rounded border-line accent-forest">
                Activ — apare în lista calculatorului
            </label>
        </x-dashboard.card>

        <div class="flex items-center gap-2 lg:col-span-2">
            <button
                type="button"
                wire:click="save"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-button bg-forest px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-forest/90 disabled:opacity-60"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <x-dashboard.icon name="check" size="size-4" />
                {{ $editing ? __('dashboard.actions.save') : 'Adaugă' }}
            </button>

            @if ($editing)
                <button
                    type="button"
                    wire:click="cancel"
                    class="rounded-button border border-line bg-paper px-4 py-2.5 text-sm font-medium text-copy transition-colors hover:bg-warm"
                >{{ __('dashboard.actions.cancel') }}</button>
            @endif
        </div>
    </div>
</div>
