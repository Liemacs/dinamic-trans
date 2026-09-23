<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFleet;
use Tests\TestCase;

/**
 * Changing a route that is already saved.
 *
 * The same calculator screen, opened over an existing row: it starts from that
 * row's own figures rather than from the current defaults, and saving overwrites
 * the row instead of adding another one beside it.
 */
class RouteEditTest extends TestCase
{
    use CreatesFleet;
    use RefreshDatabase;

    private function route(array $attributes = []): RouteCalculation
    {
        return RouteCalculation::create($attributes + [
            'origin' => 'Hâncești',
            'destination' => 'Brăila',
            'distance_km' => 220,
            'return_distance_km' => 220,
            'tonnes' => 25,
            'price_per_tonne' => 35,
            'eur_rate' => 19.9,
            'consumption' => 0.45,
            'fuel_price' => 32,
            'wear_per_km' => 2,
            'customs' => 700,
            'vignette' => 600,
            'other_costs' => 0,
            'days' => 3,
            'driver_first_day' => 2200,
            'driver_extra_day' => 1000,
        ]);
    }

    public function test_the_edit_screen_opens(): void
    {
        $route = $this->route(['vehicle_id' => $this->spreadsheetTruck()->id]);

        $this->get(route('dashboard.routes.edit', $route))
            ->assertOk()
            ->assertSee('Hâncești → Brăila');
    }

    /** The row is reachable from the list it is saved in. */
    public function test_the_list_links_to_it(): void
    {
        $route = $this->route();

        $this->get(route('dashboard.routes'))
            ->assertOk()
            ->assertSee(route('dashboard.routes.edit', $route));
    }

    /**
     * The form opens on the route's own figures. Not on the defaults — those have
     * moved since, and loading them over a saved quote would rewrite it the
     * moment the user pressed save.
     */
    public function test_it_opens_on_the_routes_own_figures_not_the_current_defaults(): void
    {
        $truck = $this->spreadsheetTruck();
        $route = $this->route(['vehicle_id' => $truck->id]);

        Setting::put(['eur_rate' => '25', 'fuel_price' => '64', 'consumption' => '0.9', 'driver_first_day' => '6600']);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->assertSet('editing', $route->id)
            ->assertSet('origin', 'Hâncești')
            ->assertSet('destination', 'Brăila')
            ->assertSet('vehicle_id', $truck->id)
            ->assertSet('distance_km', '220')
            ->assertSet('eur_rate', '19.9')
            ->assertSet('fuel_price', '32')
            ->assertSet('consumption', '0.45')
            ->assertSet('driver_first_day', '2200');
    }

    /** A stored route carries its return leg as a pickup point, so the toggle reads back on. */
    public function test_it_reopens_a_return_load(): void
    {
        $route = $this->route([
            'return_destination' => 'Galați',
            'return_tonnes' => 20,
            'return_price_per_tonne' => 30,
            'loading_day_bonus' => 500,
        ]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->assertSet('has_return_load', true)
            ->assertSet('return_destination', 'Galați')
            ->assertSet('return_tonnes', '20')
            ->assertSet('return_price_per_tonne', '30')
            ->assertSet('loading_day_bonus', '500');

        // ...and a route that came home empty leaves the toggle off.
        Livewire::test(Calculator::class, ['routeId' => $this->route()->id])
            ->assertSet('has_return_load', false);
    }

    public function test_saving_overwrites_the_row_rather_than_adding_another(): void
    {
        $truck = $this->spreadsheetTruck();
        $route = $this->route(['vehicle_id' => $truck->id]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->set('destination', 'Galați')
            ->set('price_per_tonne', '40')
            ->set('days', '4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard.routes'));

        $this->assertSame(1, RouteCalculation::query()->count());

        $fresh = $route->fresh();

        $this->assertSame('Galați', $fresh->destination);
        $this->assertSame(40.0, $fresh->price_per_tonne);
        $this->assertSame(4, $fresh->days);
        // Untouched fields keep what they were saved with.
        $this->assertSame('Hâncești', $fresh->origin);
        $this->assertSame(19.9, $fresh->eur_rate);
    }

    /** The figures follow the edit, since they are worked out from the row. */
    public function test_the_saved_figures_follow_the_edit(): void
    {
        $truck = $this->spreadsheetTruck();
        $route = $this->route(['vehicle_id' => $truck->id]);

        $before = $route->costing()->profit();

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->set('price_per_tonne', '70')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertGreaterThan($before, $route->fresh()->costing()->profit());
    }

    /** Turning the return load off drops the pickup point rather than leaving it on the row. */
    public function test_removing_the_return_load_clears_its_pickup_point(): void
    {
        $route = $this->route([
            'vehicle_id' => $this->spreadsheetTruck()->id,
            'return_destination' => 'Galați',
            'return_tonnes' => 20,
            'return_price_per_tonne' => 30,
        ]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->set('has_return_load', false)
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $route->fresh();

        $this->assertNull($fresh->return_destination);
        $this->assertSame(0.0, $fresh->return_tonnes);
        // The kilometres stay: the truck still has to get home.
        $this->assertSame(220.0, $fresh->return_distance_km);
    }

    public function test_a_failed_validation_leaves_the_row_alone(): void
    {
        $route = $this->route(['vehicle_id' => $this->spreadsheetTruck()->id]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->set('origin', '')
            ->call('save')
            ->assertHasErrors('origin')
            ->assertNoRedirect();

        $this->assertSame('Hâncești', $route->fresh()->origin);
    }

    /**
     * A truck retired since the route was saved still has to be in the select —
     * otherwise the field falls to another lorry and saving moves the route onto
     * it without anyone asking.
     */
    public function test_a_retired_truck_stays_on_the_route_it_already_ran(): void
    {
        $retired = $this->spreadsheetTruck();
        $active = Vehicle::create(['name' => 'MAN TGX', 'plate' => 'SRD 118', 'consumption' => 0.5]);

        $route = $this->route(['vehicle_id' => $retired->id]);
        $retired->update(['active' => false]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->assertSet('vehicle_id', $retired->id)
            ->assertSee($retired->label())
            ->assertSee($active->label())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($retired->id, $route->fresh()->vehicle_id);
    }

    /** While editing, the reset button is an undo — back to the row, not to the defaults. */
    public function test_reset_returns_to_the_stored_figures(): void
    {
        $route = $this->route(['vehicle_id' => $this->spreadsheetTruck()->id]);

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->set('destination', 'Galați')
            ->set('price_per_tonne', '99')
            ->call('resetForm')
            ->assertSet('editing', $route->id)
            ->assertSet('destination', 'Brăila')
            ->assertSet('price_per_tonne', '35');
    }

    /** The calculator reached from the sidebar still composes a new route. */
    public function test_the_plain_calculator_still_creates(): void
    {
        $truck = $this->spreadsheetTruck();
        $this->route(['vehicle_id' => $truck->id]);

        Livewire::test(Calculator::class)
            ->assertSet('editing', null)
            ->set('origin', 'Bălți')
            ->set('destination', 'Iași')
            ->set('vehicle_id', $truck->id)
            ->set('distance_km', '180')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame(2, RouteCalculation::query()->count());
    }
}
