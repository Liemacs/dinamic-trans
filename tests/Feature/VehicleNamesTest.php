<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\VehicleList;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The vehicle form suggests the model names already on the fleet, and folds a
 * second spelling onto the first.
 *
 * A fleet runs several of the same lorry, told apart by their plates, so
 * repeating a name is normal — repeating it differently is what makes the table
 * unreadable.
 */
class VehicleNamesTest extends TestCase
{
    use RefreshDatabase;

    private function truck(string $name, string $plate): Vehicle
    {
        return Vehicle::create(['name' => $name, 'plate' => $plate, 'consumption' => 0.45]);
    }

    public function test_it_suggests_nothing_before_the_first_vehicle(): void
    {
        $this->assertSame([], Vehicle::knownNames());
    }

    public function test_it_lists_each_model_once_however_many_lorries_run_it(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');
        $this->truck('Volvo FH 460', 'SRD 118');
        $this->truck('MAN TGX', 'KLM 922');

        $this->assertSame(['MAN TGX', 'Volvo FH 460'], Vehicle::knownNames());
    }

    public function test_a_second_spelling_folds_onto_the_first(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');

        $this->assertSame('Volvo FH 460', Vehicle::canonicalName('volvo fh 460'));
        $this->assertSame('Volvo FH 460', Vehicle::canonicalName('VOLVO FH 460'));
        $this->assertSame('Volvo FH 460', Vehicle::canonicalName('  Volvo   FH 460  '));
    }

    public function test_a_model_new_to_the_fleet_is_kept_as_typed(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');

        $this->assertSame('Mercedes Actros', Vehicle::canonicalName('Mercedes Actros'));
        $this->assertSame('', Vehicle::canonicalName('   '));
    }

    public function test_the_form_offers_the_names_on_file(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');
        $this->truck('MAN TGX', 'SRD 118');

        Livewire::test(VehicleList::class)
            ->assertSee('<datalist id="nume-camioane">', false)
            ->assertSee('Volvo FH 460')
            ->assertSee('MAN TGX');
    }

    public function test_saving_folds_the_name_onto_the_one_on_file(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');

        Livewire::test(VehicleList::class)
            ->set('name', 'volvo fh 460')
            ->assertSet('name', 'Volvo FH 460')
            ->set('plate', 'SRD 118')
            ->set('consumption', '0.47')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['Volvo FH 460'],
            Vehicle::query()->distinct()->pluck('name')->all(),
            'A doua grafie nu trebuie să creeze un al doilea model.',
        );
        // Two lorries, one model name, each with its own plate.
        $this->assertSame(2, Vehicle::count());
        $this->assertSame(['CVB 407', 'SRD 118'], Vehicle::query()->orderBy('plate')->pluck('plate')->all());
    }

    /**
     * "cvb 407" and "CVB 407" are the same registration on the same lorry, so a
     * plate is tidied into one shape rather than stored as typed.
     *
     * This is not the same as folding a plate onto a *different* truck's, which
     * nothing does — the uniqueness rule refuses that outright.
     */
    public function test_a_plate_is_tidied_into_one_shape(): void
    {
        $this->assertSame('CVB 407', Vehicle::tidyPlate('cvb 407'));
        $this->assertSame('CVB 407', Vehicle::tidyPlate('  CVB   407  '));
        $this->assertSame('', Vehicle::tidyPlate(null));

        Livewire::test(VehicleList::class)
            ->set('plate', 'cvb 407')
            ->assertSet('plate', 'CVB 407');
    }

    /**
     * Two lorries cannot share a registration. Before, the same plate typed in
     * another case produced a second truck.
     */
    public function test_a_registration_already_on_the_fleet_is_refused(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');

        Livewire::test(VehicleList::class)
            ->set('name', 'MAN TGX')
            ->set('plate', 'cvb 407')
            ->set('consumption', '0.45')
            ->call('save')
            ->assertHasErrors(['plate'])
            ->assertSee('Există deja un camion cu acest număr de înmatriculare.');

        $this->assertSame(1, Vehicle::count());
    }

    /** Saving a truck without touching its plate must not fail against itself. */
    public function test_a_truck_keeps_its_own_plate_when_edited(): void
    {
        $truck = $this->truck('Volvo FH 460', 'CVB 407');

        Livewire::test(VehicleList::class)
            ->call('edit', $truck->id)
            ->set('consumption', '0.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('CVB 407', $truck->fresh()->plate);
        $this->assertSame(0.5, $truck->fresh()->consumption);
    }

    public function test_the_form_offers_the_registrations_on_file(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');
        $this->truck('MAN TGX', 'SRD 118');

        Livewire::test(VehicleList::class)
            ->assertSee('<datalist id="numere-camioane">', false)
            ->assertSee('CVB 407')
            ->assertSee('SRD 118');
    }

    /** A plate is optional, and several trucks may be waiting for one. */
    public function test_several_trucks_may_have_no_plate_yet(): void
    {
        Livewire::test(VehicleList::class)
            ->set('name', 'Volvo FH 460')
            ->set('consumption', '0.45')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(VehicleList::class)
            ->set('name', 'MAN TGX')
            ->set('consumption', '0.44')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Vehicle::count());
    }

    public function test_a_new_model_joins_the_suggestions(): void
    {
        Livewire::test(VehicleList::class)
            ->set('name', 'Scania R 450')
            ->set('consumption', '0.44')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Scania R 450');

        $this->assertSame(['Scania R 450'], Vehicle::knownNames());
    }
}
