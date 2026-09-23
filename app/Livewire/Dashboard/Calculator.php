<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Support\RouteCosting;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The live route calculator: inputs on the left, figures on the right, updated
 * on every keystroke.
 *
 * The arithmetic is not here — it is in App\Support\RouteCosting, which a saved
 * RouteCalculation builds from its own columns too. That is what keeps the
 * number on this form and the number in the saved list the same number.
 */
class Calculator extends Component
{
    /** null while calculating a new route, the row's id while editing a saved one. */
    public ?int $editing = null;

    public string $origin = '';

    public string $destination = '';

    /** Picking a vehicle fills consumption and wear; both stay editable. */
    public ?int $vehicle_id = null;

    public string $distance_km = '';

    public string $tonnes = '';

    public string $price_per_tonne = '';

    public string $eur_rate = '';

    public string $consumption = '';

    public string $fuel_price = '';

    public string $wear_per_km = '';

    public string $customs = '';

    public string $vignette = '';

    public string $other_costs = '';

    /** Days on the road, the first one included. */
    public string $days = '1';

    public string $driver_first_day = '';

    public string $driver_extra_day = '';

    /**
     * The way home. Always asked for: the truck burns fuel coming back whether it
     * carries anything or not.
     */
    public string $return_distance_km = '';

    /**
     * True once someone types in the field above, after which it is left alone.
     * Until then it mirrors the outbound leg, because most runs come back the way
     * they went and retyping the same number is a chore.
     */
    public bool $return_distance_edited = false;

    /*
     * The return load. Off by default, because most runs come home empty — the
     * toggle keeps four fields off the screen until they are wanted.
     */
    public bool $has_return_load = false;

    public string $return_destination = '';

    public string $return_tonnes = '';

    public string $return_price_per_tonne = '';

    public string $loading_day_bonus = '';

    public string $notes = '';

    /**
     * Strings rather than floats for every numeric field: an empty input has to
     * stay empty. Bound to a float property it would come back as 0, which puts
     * a zero in a box the user has only just cleared.
     *
     * Handed a route id (from the edit screen) the form opens on that row's own
     * figures rather than on the current defaults: editing a quote has to start
     * from what was quoted.
     *
     * The id rather than the model itself. A model-typed parameter is resolved
     * out of the container when the edit screen is not the caller, and what
     * arrives then is an empty RouteCalculation rather than the null this reads
     * as "a new route".
     */
    public function mount(?int $routeId = null): void
    {
        if ($routeId !== null) {
            $this->applyRoute(RouteCalculation::findOrFail($routeId));

            return;
        }

        $this->applyDefaults();
    }

    /**
     * Most runs come home the way they went, so the return leg follows the
     * outbound one until someone says otherwise.
     */
    public function updatedDistanceKm(): void
    {
        if (! $this->return_distance_edited) {
            $this->return_distance_km = $this->distance_km;
        }
    }

    public function updatedReturnDistanceKm(): void
    {
        $this->return_distance_edited = true;
    }

    /**
     * Choosing a truck fills its consumption; choosing "no vehicle" puts the
     * generic one back.
     *
     * The wear rate is deliberately left alone. It is one flat figure for the
     * whole fleet, set in Valori implicite, because that is how the client
     * quotes — deriving it per truck made the same route cost different money
     * depending on which lorry happened to be free, and swing again whenever
     * someone corrected a truck's annual mileage. The truck's own cost per
     * kilometre is still worked out and shown, to be compared against the flat
     * rate rather than to replace it.
     */
    public function updatedVehicleId(): void
    {
        $vehicle = $this->vehicle_id !== null ? Vehicle::find($this->vehicle_id) : null;

        $this->consumption = $vehicle !== null
            ? (string) $vehicle->consumption
            : (string) Setting::get('consumption');
    }

    /**
     * The truck the form is currently pointed at, so the screen can say where the
     * consumption and the wear rate beside it came from.
     */
    #[Computed]
    public function selectedVehicle(): ?Vehicle
    {
        return $this->vehicle_id !== null ? Vehicle::find($this->vehicle_id) : null;
    }

    /**
     * Turning the return load off empties its fields rather than leaving them
     * behind the toggle, where they would keep adding kilometres and pay to a
     * route that no longer has a return leg.
     */
    public function updatedHasReturnLoad(): void
    {
        if ($this->has_return_load) {
            $this->loading_day_bonus = (string) Setting::get('loading_day_bonus');

            return;
        }

        // The kilometres stay: the truck still has to get home. Only the cargo
        // and what it pays go.
        $this->reset(['return_destination', 'return_tonnes', 'return_price_per_tonne']);
        $this->loading_day_bonus = '0';
    }

    /**
     * The trucks on offer: the active fleet, plus the one the route being edited
     * already names.
     *
     * An older route can point at a lorry since retired. Left out of the list it
     * would not simply be missing — the select would fall to another truck, and
     * saving would move the route onto it.
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()
            ->where(function ($query): void {
                $query->where('active', true);

                if ($this->vehicle_id !== null) {
                    $query->orWhere('id', $this->vehicle_id);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Rebuilt on every render, which is exactly what makes the result live.
     */
    #[Computed]
    public function costing(): RouteCosting
    {
        return RouteCosting::fromArray([
            'distance_km' => $this->distance_km,
            'consumption' => $this->consumption,
            'fuel_price' => $this->fuel_price,
            'tonnes' => $this->tonnes,
            'price_per_tonne' => $this->price_per_tonne,
            'eur_rate' => $this->eur_rate,
            'wear_per_km' => $this->wear_per_km,
            'customs' => $this->customs,
            'vignette' => $this->vignette,
            'other_costs' => $this->other_costs,
            'days' => $this->days,
            'driver_first_day' => $this->driver_first_day,
            'driver_extra_day' => $this->driver_extra_day,
            'return_distance_km' => $this->return_distance_km,
            'return_tonnes' => $this->return_tonnes,
            'return_price_per_tonne' => $this->return_price_per_tonne,
            'loading_day_bonus' => $this->loading_day_bonus,
        ]);
    }

    /**
     * Store the form: a new route, or the one being edited.
     *
     * @return bool True once the row is stored. The unsaved-changes guard
     *              (resources/js/app.js) navigates only on a true, so a failed
     *              validation leaves the user on the page with the errors.
     */
    public function save(): bool
    {
        $data = $this->validate([
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'distance_km' => ['required', 'numeric', 'min:0.01'],
            'tonnes' => ['nullable', 'numeric', 'min:0'],
            'price_per_tonne' => ['nullable', 'numeric', 'min:0'],
            'eur_rate' => ['required', 'numeric', 'min:0.0001'],
            'consumption' => ['required', 'numeric', 'min:0'],
            'fuel_price' => ['required', 'numeric', 'min:0'],
            'wear_per_km' => ['nullable', 'numeric', 'min:0'],
            'customs' => ['nullable', 'numeric', 'min:0'],
            'vignette' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'driver_first_day' => ['nullable', 'numeric', 'min:0'],
            'driver_extra_day' => ['nullable', 'numeric', 'min:0'],
            // Only demanded once the toggle is on; a run that comes home
            // empty has nothing to say about any of them.
            'return_destination' => ['nullable', 'required_if:has_return_load,true', 'string', 'max:120'],
            'return_distance_km' => ['nullable', 'numeric', 'min:0'],
            'return_tonnes' => ['nullable', 'numeric', 'min:0'],
            'return_price_per_tonne' => ['nullable', 'numeric', 'min:0'],
            'loading_day_bonus' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], messages: [
            // "Completează vehiculul" reads wrong for a list you pick from.
            'vehicle_id.required' => 'Alege vehiculul cu care se face cursa.',
            'vehicle_id.exists' => 'Vehiculul ales nu mai există.',
        ], attributes: [
            'origin' => 'plecarea',
            'vehicle_id' => 'vehiculul',
            'destination' => 'destinația',
            'distance_km' => 'distanța',
            'tonnes' => 'volumul',
            'price_per_tonne' => 'prețul per tonă',
            'eur_rate' => 'cursul EUR',
            'consumption' => 'consumul',
            'fuel_price' => 'prețul combustibilului',
            'wear_per_km' => 'uzura pe km',
            'customs' => 'vama',
            'vignette' => 'rovinieta',
            'other_costs' => 'alte costuri',
            'days' => 'numărul de zile',
            'driver_first_day' => 'salariul pe prima zi',
            'driver_extra_day' => 'salariul pe zi adițională',
            'return_destination' => 'punctul de încărcare pentru retur',
            'return_distance_km' => 'kilometrii suplimentari',
            'return_tonnes' => 'volumul de retur',
            'return_price_per_tonne' => 'prețul per tonă la retur',
            'loading_day_bonus' => 'plata pentru ziua de încărcare',
        ]);

        // The optional money fields are stored as 0 rather than null: every one
        // of them is summed, and a null in a sum is a silent hole.
        $optional = [
            'tonnes', 'price_per_tonne', 'wear_per_km',
            'customs', 'vignette', 'other_costs',
            'driver_first_day', 'driver_extra_day',
            'return_distance_km', 'return_tonnes', 'return_price_per_tonne', 'loading_day_bonus',
        ];

        foreach ($optional as $key) {
            $data[$key] = $data[$key] === '' || $data[$key] === null ? 0 : $data[$key];
        }

        // The toggle drives the form; the row records the return leg by simply
        // carrying a pickup point or not. (The toggle itself has no rule, so
        // validate() never returned it.)
        if (! $this->has_return_load) {
            $data['return_destination'] = null;
        }

        if ($this->editing !== null) {
            RouteCalculation::findOrFail($this->editing)->update($data);

            session()->flash('status', 'Ruta a fost actualizată.');

            /*
             * Back to the list the row was opened from, where the flash above is
             * read beside the updated figures. Nothing is reset first: the
             * redirect takes the form off the screen anyway.
             */
            $this->redirect(route('dashboard.routes'));

            return true;
        }

        RouteCalculation::create($data);

        session()->flash('status', 'Ruta a fost salvată.');

        $this->resetForm();

        return true;
    }

    public function resetForm(): void
    {
        if ($this->editing !== null) {
            // On a saved route this is an undo, not a clear: back to the figures
            // on the row, not to the ones a new calculation starts from.
            $this->applyRoute(RouteCalculation::findOrFail($this->editing));
            $this->resetValidation();

            return;
        }

        $this->reset([
            'origin', 'destination', 'vehicle_id', 'distance_km',
            'tonnes', 'price_per_tonne', 'other_costs', 'notes', 'days',
            'has_return_load', 'return_destination', 'return_distance_km',
            'return_distance_edited', 'return_tonnes', 'return_price_per_tonne',
        ]);
        $this->resetValidation();
        $this->applyDefaults();
    }

    public function render(): View
    {
        return view('livewire.dashboard.calculator');
    }

    /**
     * A saved route, loaded into the form exactly as it was stored.
     *
     * Deliberately none of this goes through the update hooks. Those exist to
     * help a *new* calculation along — mirroring the outbound leg, pulling a
     * truck's consumption — and running them over a stored row would quietly
     * replace figures that were already quoted to a customer.
     */
    private function applyRoute(RouteCalculation $route): void
    {
        $this->editing = $route->id;

        $this->origin = $route->origin;
        $this->destination = $route->destination;
        $this->vehicle_id = $route->vehicle_id;
        $this->distance_km = (string) $route->distance_km;
        $this->tonnes = (string) $route->tonnes;
        $this->price_per_tonne = (string) $route->price_per_tonne;
        $this->eur_rate = (string) $route->eur_rate;
        $this->consumption = (string) $route->consumption;
        $this->fuel_price = (string) $route->fuel_price;
        $this->wear_per_km = (string) $route->wear_per_km;
        $this->customs = (string) $route->customs;
        $this->vignette = (string) $route->vignette;
        $this->other_costs = (string) $route->other_costs;
        $this->days = (string) $route->days;
        $this->driver_first_day = (string) $route->driver_first_day;
        $this->driver_extra_day = (string) $route->driver_extra_day;
        $this->notes = (string) $route->notes;

        // A stored route records its return leg by carrying a pickup point or
        // not, which is what the toggle reads back here.
        $this->has_return_load = $route->return_destination !== null;
        $this->return_destination = (string) $route->return_destination;
        $this->return_tonnes = (string) $route->return_tonnes;
        $this->return_price_per_tonne = (string) $route->return_price_per_tonne;
        $this->loading_day_bonus = (string) $route->loading_day_bonus;

        // The kilometres home came off the row. Marking them edited keeps the
        // outbound field from overwriting them the first time it is touched.
        $this->return_distance_km = (string) $route->return_distance_km;
        $this->return_distance_edited = true;

        $this->resetValidation();
    }

    /**
     * The figures a new calculation starts from (Flotă → Valori implicite).
     */
    private function applyDefaults(): void
    {
        foreach (['eur_rate', 'consumption', 'fuel_price', 'wear_per_km', 'customs', 'vignette', 'driver_first_day', 'driver_extra_day'] as $key) {
            $this->{$key} = (string) Setting::get($key);
        }

        $this->other_costs = $this->other_costs !== '' ? $this->other_costs : '0';

        // The loading day is only paid on a route that has one, so it starts at
        // zero and the toggle fills it from the settings.
        $this->loading_day_bonus = $this->has_return_load
            ? (string) Setting::get('loading_day_bonus')
            : '0';
    }
}
