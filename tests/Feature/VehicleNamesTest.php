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
     * A plate is each lorry's own. Folding it onto another's would quietly move
     * the registration of one truck onto a second.
     */
    public function test_the_plate_is_never_folded(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');

        Livewire::test(VehicleList::class)
            ->set('name', 'MAN TGX')
            ->set('plate', 'cvb 407')
            ->set('consumption', '0.45')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['CVB 407', 'cvb 407'],
            Vehicle::query()->orderBy('id')->pluck('plate')->all(),
        );
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
