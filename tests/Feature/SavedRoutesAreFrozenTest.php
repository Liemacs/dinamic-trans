<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Livewire\Dashboard\Defaults;
use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFleet;
use Tests\TestCase;

/**
 * A saved route is a record of what was quoted, not a live recalculation.
 *
 * Every figure it needs is copied onto its own row at save time, so moving the
 * parameters afterwards — a new exchange rate, a fuel price rise, a driver pay
 * increase, a retuned truck — can never rewrite a margin that was already agreed
 * with a customer.
 *
 * This is the test that holds that promise. It changes *everything* and asserts
 * that *nothing* on the saved route moves.
 */
class SavedRoutesAreFrozenTest extends TestCase
{
    use CreatesFleet;
    use RefreshDatabase;

    /** The Hâncești → Brăila run from the source spreadsheet. */
    private function saveTheSpreadsheetRoute(?Vehicle $vehicle = null): RouteCalculation
    {
        // A route cannot be saved without one, so the caller's truck stands in
        // for the default only when it has something particular to prove.
        $vehicle ??= $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $vehicle->id)
            ->set('distance_km', '440')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors();

        return RouteCalculation::sole();
    }

    /**
     * Every derived figure the screens show, so a leak anywhere is caught rather
     * than only the headline profit.
     *
     * @return array<string, float>
     */
    private function figures(RouteCalculation $route): array
    {
        $costing = $route->costing();

        return [
            'revenue_eur' => round($costing->revenueEur(), 4),
            'revenue' => round($costing->revenue(), 4),
            'fuel_litres' => round($costing->fuelLitres(), 4),
            'fuel_cost' => round($costing->fuelCost(), 4),
            'driver_salary' => round($costing->driverSalary(), 4),
            'wear_cost' => round($costing->wearCost(), 4),
            'customs' => round($costing->customs, 4),
            'vignette' => round($costing->vignette, 4),
            'total_cost' => round($costing->totalCost(), 4),
            'profit' => round($costing->profit(), 4),
            'margin' => round($costing->margin(), 4),
            'cost_per_km' => round($costing->costPerKm(), 4),
            'profit_per_km' => round($costing->profitPerKm(), 4),
            'break_even_per_tonne' => round($costing->breakEvenPerTonneEur(), 4),
        ];
    }

    public function test_changing_every_default_leaves_a_saved_route_untouched(): void
    {
        $route = $this->saveTheSpreadsheetRoute();
        $before = $this->figures($route);

        // Move every parameter, and move each one to something clearly different
        // so a leak shows up as a wrong number rather than a coincidence.
        Setting::put([
            'eur_rate' => '25',
            'consumption' => '0.9',
            'fuel_price' => '64',
            'wear_per_km' => '8',
            'customs' => '2100',
            'vignette' => '1800',
            'driver_first_day' => '6600',
            'driver_extra_day' => '3000',
            'gps_monthly' => '1200',
        ]);

        $this->assertSame($before, $this->figures($route->fresh()));
    }

    /**
     * The same guarantee through the screen the user actually edits, rather than
     * through the model — the Defaults form is what they will change in practice.
     */
    public function test_saving_the_defaults_screen_leaves_a_saved_route_untouched(): void
    {
        $route = $this->saveTheSpreadsheetRoute();
        $before = $this->figures($route);

        Livewire::test(Defaults::class)
            ->set('eur_rate', '25')
            ->set('consumption', '0.9')
            ->set('fuel_price', '64')
            ->set('wear_per_km', '8')
            ->set('customs', '2100')
            ->set('vignette', '1800')
            ->set('driver_first_day', '6600')
            ->set('driver_extra_day', '3000')
            ->set('gps_monthly', '1200')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($before, $this->figures($route->fresh()));
    }

    public function test_retuning_the_vehicle_leaves_a_saved_route_untouched(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo FH 460', 'consumption' => 0.45,
            'insurance_annual' => 18000, 'service_annual' => 24000,
            'tyres_annual' => 12000, 'gps_monthly' => 400, 'annual_km' => 60000,
        ]);

        $route = $this->saveTheSpreadsheetRoute($vehicle);
        $before = $this->figures($route);

        $vehicle->update([
            'consumption' => 0.9,
            'insurance_annual' => 90000,
            'service_annual' => 90000,
            'tyres_annual' => 90000,
            'gps_monthly' => 1200,
            'annual_km' => 10000,
        ]);

        $this->assertSame($before, $this->figures($route->fresh()));
    }

    /**
     * Deleting a truck must not take the routes it pulled down with it, nor blank
     * the figures they copied off it — the foreign key nulls, the columns stay.
     */
    public function test_deleting_the_vehicle_leaves_a_saved_route_untouched(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo FH 460', 'consumption' => 0.45,
            'insurance_annual' => 18000, 'service_annual' => 24000,
            'tyres_annual' => 12000, 'gps_monthly' => 400, 'annual_km' => 60000,
        ]);

        $route = $this->saveTheSpreadsheetRoute($vehicle);
        $before = $this->figures($route);

        $vehicle->delete();

        $fresh = $route->fresh();

        $this->assertNull($fresh->vehicle_id);
        $this->assertSame($before, $this->figures($fresh));
    }

    /**
     * The parameters are still a starting point for the *next* route — freezing
     * the old ones must not have frozen the form as well.
     */
    public function test_a_new_route_still_picks_up_the_changed_defaults(): void
    {
        $this->saveTheSpreadsheetRoute();

        Setting::put(['eur_rate' => '25', 'fuel_price' => '64']);

        Livewire::test(Calculator::class)
            ->assertSet('eur_rate', '25')
            ->assertSet('fuel_price', '64');
    }
}
