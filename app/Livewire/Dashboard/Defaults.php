<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Setting;
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

    public string $eur_rate = '';

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
        foreach (array_keys(Setting::defaults()) as $key) {
            $this->{$key} = (string) Setting::get($key);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'currency' => ['required', 'string', 'max:8'],
            'eur_rate' => ['required', 'numeric', 'min:0.0001'],
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
            'eur_rate' => 'cursul EUR',
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

        Setting::put($data);

        session()->flash('status', 'Valorile implicite au fost salvate.');
    }

    public function render(): View
    {
        return view('livewire.dashboard.defaults');
    }
}
