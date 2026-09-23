<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Livewire\Dashboard\VehicleList;
use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesFleet;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesFleet;
    use RefreshDatabase;

    public static function sectionProvider(): array
    {
        return [
            'overview' => ['dashboard.overview'],
            'calculator' => ['dashboard.calculator'],
            'routes' => ['dashboard.routes'],
            'report' => ['dashboard.report'],
            'vehicles' => ['dashboard.fleet.vehicles'],
            'defaults' => ['dashboard.fleet.defaults'],
        ];
    }

    #[DataProvider('sectionProvider')]
    public function test_every_section_in_the_sidebar_opens(string $route): void
    {
        $this->get(route($route))->assertOk();
    }

    public function test_the_calculator_starts_from_the_saved_parameters(): void
    {
        Setting::put(['fuel_price' => '34', 'consumption' => '0.5', 'eur_rate' => '20.1']);

        Livewire::test(Calculator::class)
            ->assertSet('fuel_price', '34')
            ->assertSet('consumption', '0.5')
            ->assertSet('eur_rate', '20.1')
            ->assertSet('driver_first_day', '2200')
            ->assertSet('driver_extra_day', '1000');
    }

    public function test_saving_the_spreadsheet_route_stores_it_and_clears_the_form(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            // 220 each way. The spreadsheet's single "Distanța" of 440 was the
            // whole journey, so entering the leg and letting the return mirror it
            // lands on the same total.
            ->set('distance_km', '220')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('origin', '');

        $route = RouteCalculation::sole();
        $costing = $route->costing();

        $this->assertSame('Hâncești → Brăila', $route->label());
        $this->assertSame(220.0, $costing->distanceKm);
        $this->assertSame(220.0, $costing->returnDistanceKm);
        $this->assertSame(440.0, $costing->totalDistanceKm());
        $this->assertSame(198.0, round($costing->fuelLitres(), 2));
        $this->assertSame(4696.5, round($costing->profit(), 2));
        $this->assertSame(4200.0, round($costing->driverSalary(), 2));
    }

    /**
     * Most runs come back the way they went, so the return leg follows the
     * outbound one — and the truck is charged for both.
     */
    public function test_the_return_leg_mirrors_the_outbound_one_until_it_is_edited(): void
    {
        Livewire::test(Calculator::class)
            ->set('distance_km', '440')
            ->assertSet('return_distance_km', '440')
            // Edited by hand, and from then on left alone.
            ->set('return_distance_km', '380')
            ->set('distance_km', '500')
            ->assertSet('return_distance_km', '380');
    }

    /**
     * Charging only the outbound leg would halve the fuel and the wear, which is
     * the mistake this split exists to prevent.
     */
    public function test_the_way_home_is_charged_fuel_and_wear(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '220')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            // Zeroing the way home is allowed — a delivery the truck does not
            // come back from — and it must visibly cost less.
            ->set('return_distance_km', '0')
            ->call('save')
            ->assertHasNoErrors();

        $oneWay = RouteCalculation::sole()->costing();

        $this->assertSame(220.0, $oneWay->totalDistanceKm());
        $this->assertSame(99.0, round($oneWay->fuelLitres(), 2));

        // Against the round trip: 220 more km at 0.45 L/km and 2 lei/km of wear
        // is 3 608 lei, and the one-way run keeps it.
        $this->assertSame(8304.5, round($oneWay->profit(), 2));
    }

    public function test_a_route_without_a_distance_is_refused(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '')
            ->call('save')
            ->assertHasErrors(['distance_km']);

        $this->assertSame(0, RouteCalculation::count());
    }

    public function test_a_route_of_zero_days_is_refused(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '440')
            ->set('days', '0')
            ->call('save')
            ->assertHasErrors(['days']);
    }

    /**
     * Retuning the fleet or moving the exchange rate must not rewrite the margin
     * on a route already quoted: each row keeps the numbers it was calculated
     * with.
     */
    public function test_a_saved_route_keeps_its_own_figures_when_the_world_moves(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo', 'consumption' => 0.45,
            'insurance_annual' => 18000, 'service_annual' => 24000,
            'tyres_annual' => 12000, 'gps_monthly' => 400, 'annual_km' => 60000,
        ]);

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

        $before = RouteCalculation::sole()->costing()->profit();

        $vehicle->update(['insurance_annual' => 90000, 'annual_km' => 20000]);
        Setting::put(['eur_rate' => '25', 'driver_first_day' => '5000']);

        $this->assertSame(round($before, 2), round(RouteCalculation::sole()->costing()->profit(), 2));
    }

    public function test_a_new_vehicle_starts_on_the_fleet_gps_subscription(): void
    {
        Setting::put(['gps_monthly' => '450']);

        Livewire::test(VehicleList::class)->assertSet('gps_monthly', '450');
    }

    public function test_the_vehicle_form_previews_the_annual_total_before_saving(): void
    {
        Livewire::test(VehicleList::class)
            ->set('insurance_annual', '18000')
            ->set('service_annual', '24000')
            ->set('tyres_annual', '12000')
            ->set('gps_monthly', '400')
            ->assertSee('58.800')          // annual total, GPS × 12 included
            ->assertDontSee('MDL/km');     // no per-kilometre rate is derived here
    }

    public function test_saving_a_vehicle_records_every_expense_line(): void
    {
        Livewire::test(VehicleList::class)
            ->set('name', 'Volvo FH 460')
            ->set('consumption', '0.45')
            ->set('insurance_annual', '18000')
            ->set('service_annual', '24000')
            ->set('tyres_annual', '12000')
            ->set('gps_monthly', '400')
            ->call('save')
            ->assertHasNoErrors();

        $vehicle = Vehicle::sole();

        $this->assertSame(4800.0, $vehicle->gpsAnnual());
        $this->assertSame(58800.0, $vehicle->annualTotal());
    }

    public function test_saving_a_route_with_a_return_load_records_the_whole_journey(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '440')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->set('has_return_load', true)
            ->set('return_destination', 'Galați')
            ->set('return_distance_km', '160')
            ->set('return_tonnes', '20')
            ->set('return_price_per_tonne', '30')
            ->call('save')
            ->assertHasNoErrors();

        $route = RouteCalculation::sole();
        $costing = $route->costing();

        $this->assertSame('Hâncești → Brăila → Galați', $route->label());
        $this->assertSame(600.0, $costing->totalDistanceKm());
        // The toggle filled the loading day from the settings.
        $this->assertSame(1200.0, $costing->loadingDayBonus);
        $this->assertSame(5400.0, round($costing->driverSalary(), 2));
        $this->assertSame(12812.5, round($costing->profit(), 2));
    }

    public function test_a_return_load_needs_somewhere_to_load(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '440')
            ->set('days', '3')
            ->set('has_return_load', true)
            ->set('return_destination', '')
            ->call('save')
            ->assertHasErrors(['return_destination']);

        $this->assertSame(0, RouteCalculation::count());
    }

    /**
     * Turning the toggle back off must empty the fields behind it — otherwise a
     * route with no return leg would still be charged its kilometres and its
     * loading day.
     */
    public function test_switching_the_return_load_off_clears_what_it_hid(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '440')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->set('has_return_load', true)
            ->set('return_destination', 'Galați')
            ->set('return_distance_km', '160')
            ->set('return_tonnes', '20')
            ->set('return_price_per_tonne', '30')
            ->set('has_return_load', false)
            ->assertSet('return_destination', '')
            // The kilometres stay: the truck still has to get home. Only the
            // cargo and what it pays go with the toggle.
            ->assertSet('return_distance_km', '160')
            ->assertSet('loading_day_bonus', '0')
            ->call('save')
            ->assertHasNoErrors();

        $route = RouteCalculation::sole();

        $this->assertNull($route->return_destination);
        $this->assertFalse($route->costing()->hasReturnLoad());
        // Still 600 km driven, but nothing earned on the way back.
        $this->assertSame(600.0, $route->costing()->totalDistanceKm());
        $this->assertSame(875.0, round($route->costing()->revenueEur(), 2));
    }

    public function test_a_route_cannot_be_saved_without_a_vehicle(): void
    {
        $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '220')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            // Everything else is in; only the truck is missing.
            ->call('save')
            ->assertHasErrors(['vehicle_id'])
            ->assertSee('Alege vehiculul cu care se face cursa.');

        $this->assertSame(0, RouteCalculation::count());
    }

    public function test_deselecting_the_vehicle_blocks_the_save_again(): void
    {
        $truck = $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '220')
            ->set('vehicle_id', $truck->id)
            ->set('vehicle_id', null)
            ->call('save')
            ->assertHasErrors(['vehicle_id']);

        $this->assertSame(0, RouteCalculation::count());
    }

    /**
     * A truck deleted between choosing it and saving must not sneak through as a
     * dangling id.
     */
    public function test_a_vehicle_that_no_longer_exists_is_refused(): void
    {
        $truck = $this->spreadsheetTruck();

        $component = Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('distance_km', '220')
            ->set('vehicle_id', $truck->id);

        $truck->delete();

        $component->call('save')
            ->assertHasErrors(['vehicle_id'])
            ->assertSee('Vehiculul ales nu mai există.');
    }

    /**
     * With an empty fleet the form cannot be completed at all, so it says so at
     * the top rather than letting someone fill it in and fail at the end.
     */
    public function test_an_empty_fleet_is_announced_on_the_calculator(): void
    {
        Livewire::test(Calculator::class)
            ->assertSee('Nu ai niciun vehicul activ')
            ->assertSee('Adaugă un camion');
    }

    public function test_the_notice_goes_once_there_is_a_vehicle(): void
    {
        $this->spreadsheetTruck();

        Livewire::test(Calculator::class)->assertDontSee('Nu ai niciun vehicul activ');
    }

    /**
     * Historical rows keep their null: the column stays nullable so that deleting
     * a truck orphans its routes rather than taking them with it.
     */
    public function test_an_older_route_without_a_vehicle_still_reads_back(): void
    {
        $route = RouteCalculation::create([
            'origin' => 'Hâncești', 'destination' => 'Brăila',
            'vehicle_id' => null,
            'distance_km' => 220, 'return_distance_km' => 220,
            'tonnes' => 25, 'price_per_tonne' => 35, 'eur_rate' => 19.9,
            'consumption' => 0.45, 'fuel_price' => 32, 'wear_per_km' => 2,
            'customs' => 700, 'vignette' => 600,
            'days' => 3, 'driver_first_day' => 2200, 'driver_extra_day' => 1000,
        ]);

        $this->assertNull($route->vehicle);
        $this->assertSame(4696.5, round($route->costing()->profit(), 2));

        $this->get(route('dashboard.routes'))->assertOk()->assertSee('Fără vehicul');
    }

    /**
     * A truck hands over its consumption and nothing else. The wear rate is one
     * flat figure for the whole fleet — deriving it per truck made the same route
     * cost different money depending on which lorry was free, and swing again
     * whenever someone corrected a truck's annual mileage.
     */
    public function test_choosing_a_vehicle_takes_its_consumption_and_leaves_the_wear_alone(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo FH 460', 'consumption' => 0.48,
            'insurance_annual' => 18000, 'service_annual' => 24000,
            'tyres_annual' => 12000, 'gps_monthly' => 400, 'annual_km' => 60000,
        ]);

        Livewire::test(Calculator::class)
            ->assertSet('wear_per_km', '2')
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('consumption', '0.48')
            // 0.98 lei/km is what this truck really costs, and it stays out of it.
            ->assertSet('wear_per_km', '2');
    }

    public function test_clearing_the_vehicle_restores_the_generic_consumption(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo FH 460', 'consumption' => 0.48,
            'insurance_annual' => 18000, 'annual_km' => 60000,
        ]);

        Livewire::test(Calculator::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('consumption', '0.48')
            ->set('vehicle_id', null)
            ->assertSet('consumption', '0.45')
            ->assertSet('wear_per_km', '2');
    }

    /**
     * Retuning a truck's annual mileage used to move the wear on every route
     * calculated with it. Now it moves nothing but the reported figure.
     */
    public function test_a_trucks_annual_mileage_no_longer_moves_the_route(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'MAN TGX', 'consumption' => 0.45,
            'insurance_annual' => 15000, 'service_annual' => 19000,
            'tyres_annual' => 9000, 'gps_monthly' => 400, 'annual_km' => 48000,
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('vehicle_id', $vehicle->id)
            ->assertSet('wear_per_km', '2');

        $vehicle->update(['annual_km' => 148000]);

        $component->set('vehicle_id', null)->set('vehicle_id', $vehicle->id)
            ->assertSet('wear_per_km', '2');
    }

    /**
     * The consumption field sits in a card further down, off the screen where the
     * select is used, so choosing a truck has to report what it did.
     */
    public function test_choosing_a_vehicle_shows_the_consumption_it_took(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Volvo FH 460', 'plate' => 'ABC 123', 'consumption' => 0.45,
            'insurance_annual' => 18000, 'service_annual' => 24000,
            'tyres_annual' => 12000, 'other_annual' => 6000,
            'gps_monthly' => 400, 'annual_km' => 60000,
        ]);

        Livewire::test(Calculator::class)
            ->assertDontSee('Preluat din')
            ->set('vehicle_id', $vehicle->id)
            ->assertSee('Preluat din Volvo FH 460')
            ->assertSee('0,45 L/km')
            // Nothing about wear: it is the flat rate from Valori implicite and
            // the truck has no say in it.
            ->assertDontSee('Camionul costă în realitate')
            ->set('vehicle_id', null)
            ->assertDontSee('Preluat din');
    }

    public function test_the_wear_stays_flat_whatever_the_truck(): void
    {
        $cheap = Vehicle::create(['name' => 'Remorcă nouă', 'consumption' => 0.5, 'insurance_annual' => 9000]);
        $dear = Vehicle::create(['name' => 'Volvo vechi', 'consumption' => 0.6, 'insurance_annual' => 200000]);

        Livewire::test(Calculator::class)
            ->set('vehicle_id', $cheap->id)
            ->assertSet('consumption', '0.5')
            ->assertSet('wear_per_km', '2')
            ->set('vehicle_id', $dear->id)
            ->assertSet('consumption', '0.6')
            ->assertSet('wear_per_km', '2');
    }

    /**
     * The three town fields offer what has already been driven to, and a second
     * spelling of the same town never reaches the database.
     */
    public function test_the_calculator_suggests_places_already_on_file(): void
    {
        $truck = $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Chișinău')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $truck->id)
            ->set('distance_km', '220')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors();

        // The saved names come back as suggestions on the next form.
        Livewire::test(Calculator::class)
            ->assertSee('<datalist id="localitati">', false)
            ->assertSee('Chișinău')
            ->assertSee('Brăila');
    }

    public function test_a_second_spelling_is_stored_as_the_first(): void
    {
        $truck = $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Chișinău')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $truck->id)
            ->set('distance_km', '220')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors();

        // Typed without diacritics the second time round.
        Livewire::test(Calculator::class)
            ->set('origin', 'chisinau')
            ->assertSet('origin', 'Chișinău')
            ->set('destination', 'BRAILA')
            ->assertSet('destination', 'Brăila')
            ->set('vehicle_id', $truck->id)
            ->set('distance_km', '300')
            ->set('days', '2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['Chișinău'],
            RouteCalculation::query()->distinct()->pluck('origin')->all(),
            'Al doilea mod de scriere nu trebuie să creeze o a doua localitate.',
        );
        $this->assertSame(['Brăila'], RouteCalculation::query()->distinct()->pluck('destination')->all());
    }

    public function test_a_town_nobody_has_driven_to_is_accepted_as_typed(): void
    {
        $truck = $this->spreadsheetTruck();

        Livewire::test(Calculator::class)
            ->set('origin', 'Chișinău')
            ->set('destination', 'Sfântu Gheorghe')
            ->set('vehicle_id', $truck->id)
            ->set('distance_km', '220')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Sfântu Gheorghe', RouteCalculation::sole()->destination);
    }
}
