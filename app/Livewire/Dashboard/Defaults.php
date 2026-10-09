<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Setting;
use App\Support\BnmRates;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The figures a new calculation starts from — the "PARAMETRI" block of the
 * source spreadsheet. Everything here is a starting point, never a constraint:
 * each one stays editable on the calculator itself.
 */
class Defaults extends Component
{
    public string $currency = '';

    public string $quote_currency = '';

    public string $eur_rate = '';

    public string $usd_rate = '';

    public string $consumption = '';

    public string $fuel_price = '';

    public string $wear_per_km = '';

    public string $customs = '';

    public string $vignette = '';

    public string $driver_first_day = '';

    public string $driver_extra_day = '';

    public string $loading_day_bonus = '';

    public string $gps_monthly = '';

    public function mount(): void
    {
        // Today's BNM rates first, so the screen opens on them rather than on
        // yesterday's. A no-op once they are already in, and cached either way.
        BnmRates::refreshSettings();

        $this->loadSettings();
    }

    /**
     * Fetch the BNM rates now, even if today's are already in — for when someone
     * has typed over them and wants the official ones back.
     */
    public function refreshFromBnm(): void
    {
        if (BnmRates::refreshSettings(force: true)) {
            $this->eur_rate = (string) Setting::get('eur_rate');
            $this->usd_rate = (string) Setting::get('usd_rate');

            session()->flash('status', 'Cursurile au fost preluate de la BNM.');

            return;
        }

        session()->flash('status', 'BNM nu a răspuns. Au rămas ultimele cursuri cunoscute.');
    }

    private function loadSettings(): void
    {
        foreach (array_keys(Setting::defaults()) as $key) {
            $this->{$key} = (string) Setting::get($key);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'currency' => ['required', 'string', 'max:8'],
            'quote_currency' => ['required', 'string', Rule::in(array_keys(Setting::QUOTE_CURRENCIES))],
            'eur_rate' => ['required', 'numeric', 'min:0.0001'],
            'usd_rate' => ['required', 'numeric', 'min:0.0001'],
            'consumption' => ['required', 'numeric', 'min:0'],
            'fuel_price' => ['required', 'numeric', 'min:0'],
            'wear_per_km' => ['required', 'numeric', 'min:0'],
            'customs' => ['required', 'numeric', 'min:0'],
            'vignette' => ['required', 'numeric', 'min:0'],
            'driver_first_day' => ['required', 'numeric', 'min:0'],
            'driver_extra_day' => ['required', 'numeric', 'min:0'],
            'loading_day_bonus' => ['required', 'numeric', 'min:0'],
            'gps_monthly' => ['required', 'numeric', 'min:0'],
        ], attributes: [
            'currency' => 'moneda',
            'quote_currency' => 'moneda implicită',
            'eur_rate' => 'cursul EUR',
            'usd_rate' => 'cursul USD',
            'consumption' => 'consumul',
            'fuel_price' => 'prețul combustibilului',
            'wear_per_km' => 'uzura pe km',
            'customs' => 'vama',
            'vignette' => 'rovinieta',
            'driver_first_day' => 'salariul pe prima zi',
            'driver_extra_day' => 'salariul pe zi adițională',
            'loading_day_bonus' => 'plata pentru ziua de încărcare',
            'gps_monthly' => 'abonamentul GPS',
        ]);

        /*
         * The screen saves every field at once, so a rate is only "set by hand"
         * if it actually moved. Otherwise changing the fuel price would quietly
         * switch the rates off BNM until tomorrow.
         */
        $ratesMoved = abs((float) $data['eur_rate'] - Setting::number('eur_rate')) > 0.00001
            || abs((float) $data['usd_rate'] - Setting::number('usd_rate')) > 0.00001;

        Setting::put($data);

        if ($ratesMoved) {
            // Holds for the rest of the day; BNM takes over again tomorrow.
            Setting::put([
                'rates_source' => 'manual',
                'rates_date' => now(BnmRates::TIMEZONE)->format('Y-m-d'),
            ]);
        }

        session()->flash('status', 'Valorile implicite au fost salvate.');
    }

    public function render(): View
    {
        return view('livewire.dashboard.defaults');
    }
}
