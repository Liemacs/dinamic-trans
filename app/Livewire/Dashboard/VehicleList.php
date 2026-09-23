<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The fleet and what each truck costs to keep on the road, plus the one form
 * that both adds to it and edits it.
 *
 * A single inline form rather than a separate create screen and edit screen: the
 * list beside it is the context you want while typing an insurance premium.
 */
class VehicleList extends Component
{
    /** null while adding, the vehicle's id while editing. */
    public ?int $editing = null;

    public string $name = '';

    public string $plate = '';

    public string $consumption = '';

    public string $insurance_annual = '';

    public string $service_annual = '';

    public string $tyres_annual = '';

    public string $other_annual = '';

    public string $gps_monthly = '';

    public bool $active = true;

    public function mount(): void
    {
        $this->applyDefaults();
    }

    /**
     * The model names already on the fleet, for the suggestion list on the name
     * field.
     *
     * @return list<string>
     */
    #[Computed]
    public function names(): array
    {
        return Vehicle::knownNames();
    }

    /*
     * The name snaps onto a spelling already on the fleet, so a second "volvo
     * fh460" does not sit in the table beside "Volvo FH 460" looking like a
     * different lorry. The plate is left alone: it is each truck's own.
     */
    public function updatedName(): void
    {
        $this->name = Vehicle::canonicalName($this->name);
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()->orderBy('name')->get();
    }

    /**
     * A live preview of the vehicle being typed, so the annual total and the
     * derived per-kilometre rate move as the fields are filled in — the same
     * reason the calculator recomputes on every keystroke.
     */
    #[Computed]
    public function draft(): Vehicle
    {
        $number = fn (string $key): float => is_numeric($this->{$key}) ? (float) $this->{$key} : 0.0;

        return new Vehicle([
            'consumption' => $number('consumption'),
            'insurance_annual' => $number('insurance_annual'),
            'service_annual' => $number('service_annual'),
            'tyres_annual' => $number('tyres_annual'),
            'other_annual' => $number('other_annual'),
            'gps_monthly' => $number('gps_monthly'),
        ]);
    }

    public function edit(int $id): void
    {
        $vehicle = Vehicle::findOrFail($id);

        $this->editing = $vehicle->id;
        $this->name = $vehicle->name;
        $this->plate = (string) $vehicle->plate;
        $this->consumption = (string) $vehicle->consumption;
        $this->insurance_annual = (string) $vehicle->insurance_annual;
        $this->service_annual = (string) $vehicle->service_annual;
        $this->tyres_annual = (string) $vehicle->tyres_annual;
        $this->other_annual = (string) $vehicle->other_annual;
        $this->gps_monthly = (string) $vehicle->gps_monthly;
        $this->active = $vehicle->active;

        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate' => ['nullable', 'string', 'max:20'],
            'consumption' => ['required', 'numeric', 'min:0'],
            'insurance_annual' => ['nullable', 'numeric', 'min:0'],
            'service_annual' => ['nullable', 'numeric', 'min:0'],
            'tyres_annual' => ['nullable', 'numeric', 'min:0'],
            'other_annual' => ['nullable', 'numeric', 'min:0'],
            'gps_monthly' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ], attributes: [
            'name' => 'numele',
            'plate' => 'numărul de înmatriculare',
            'consumption' => 'consumul',
            'insurance_annual' => 'asigurarea',
            'service_annual' => 'service-ul',
            'tyres_annual' => 'anvelopele',
            'other_annual' => 'alte cheltuieli',
            'gps_monthly' => 'GPS-ul',
        ]);

        foreach (['insurance_annual', 'service_annual', 'tyres_annual', 'other_annual', 'gps_monthly'] as $key) {
            $data[$key] = $data[$key] === '' || $data[$key] === null ? 0 : $data[$key];
        }

        /*
         * Folded again here rather than trusting the update hook. A deferred
         * `wire:model` can carry its value in on the very request that calls
         * save(), and the whole point is that no second spelling reaches the
         * table.
         */
        $data['name'] = Vehicle::canonicalName($data['name']);

        if ($this->editing !== null) {
            Vehicle::findOrFail($this->editing)->update($data);
            session()->flash('status', 'Vehiculul a fost actualizat.');
        } else {
            Vehicle::create($data);
            session()->flash('status', 'Vehiculul a fost adăugat.');
        }

        $this->cancel();

        // Both lists are computed properties, so they have to be told the table
        // under them moved; without this the row just saved is missing until a
        // reload, and a new model name is absent from the suggestions.
        unset($this->vehicles, $this->names);
    }

    public function delete(int $id): void
    {
        Vehicle::query()->whereKey($id)->delete();

        if ($this->editing === $id) {
            $this->cancel();
        }

        unset($this->vehicles, $this->names);

        session()->flash('status', 'Vehiculul a fost șters.');
    }

    public function cancel(): void
    {
        $this->reset([
            'editing', 'name', 'plate', 'consumption', 'insurance_annual',
            'service_annual', 'tyres_annual', 'other_annual', 'gps_monthly',
            'active',
        ]);
        $this->resetValidation();
        $this->applyDefaults();
    }

    public function render(): View
    {
        return view('livewire.dashboard.vehicle-list');
    }

    /**
     * A new truck starts on the fleet-wide GPS subscription and consumption,
     * which are the two figures that are almost always the same across trucks.
     */
    private function applyDefaults(): void
    {
        $this->gps_monthly = (string) Setting::get('gps_monthly');
        $this->consumption = (string) Setting::get('consumption');
    }
}
