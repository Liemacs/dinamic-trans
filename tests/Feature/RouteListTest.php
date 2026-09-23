<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\RouteList;
use App\Models\RouteCalculation;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The saved-routes list: one search box over everything a row says about where a
 * route went and what ran it, and a pager once there are more rows than fit.
 */
class RouteListTest extends TestCase
{
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
            'days' => 3,
            'driver_first_day' => 2200,
            'driver_extra_day' => 1000,
        ]);
    }

    private function truck(string $name, string $plate): Vehicle
    {
        return Vehicle::create(['name' => $name, 'plate' => $plate, 'consumption' => 0.45]);
    }

    public function test_it_searches_by_origin_and_destination(): void
    {
        $this->route(['origin' => 'Hâncești', 'destination' => 'Brăila']);
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași']);

        Livewire::test(RouteList::class)
            ->set('search', 'Bălți')
            ->assertSee('Bălți → Iași')
            ->assertDontSee('Hâncești → Brăila')
            ->set('search', 'Brăila')
            ->assertSee('Hâncești → Brăila')
            ->assertDontSee('Bălți → Iași');
    }

    /** The third point a return load adds is a place the route went, so it counts. */
    public function test_it_searches_by_the_return_pickup_point(): void
    {
        $this->route(['return_destination' => 'Galați', 'return_tonnes' => 20, 'return_price_per_tonne' => 30]);
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași']);

        Livewire::test(RouteList::class)
            ->set('search', 'Galați')
            ->assertSee('Hâncești → Brăila → Galați')
            ->assertDontSee('Bălți → Iași');
    }

    public function test_it_searches_by_vehicle_name(): void
    {
        $volvo = $this->truck('Volvo FH 460', 'CVB 407');
        $man = $this->truck('MAN TGX', 'SRD 118');

        $this->route(['origin' => 'Hâncești', 'destination' => 'Brăila', 'vehicle_id' => $volvo->id]);
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași', 'vehicle_id' => $man->id]);

        Livewire::test(RouteList::class)
            ->set('search', 'Volvo')
            ->assertSee('Hâncești → Brăila')
            ->assertDontSee('Bălți → Iași');
    }

    public function test_it_searches_by_vehicle_plate(): void
    {
        $volvo = $this->truck('Volvo FH 460', 'CVB 407');
        $man = $this->truck('MAN TGX', 'SRD 118');

        $this->route(['origin' => 'Hâncești', 'destination' => 'Brăila', 'vehicle_id' => $volvo->id]);
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași', 'vehicle_id' => $man->id]);

        Livewire::test(RouteList::class)
            ->set('search', 'SRD')
            ->assertSee('Bălți → Iași')
            ->assertDontSee('Hâncești → Brăila');
    }

    /**
     * The whole OR set has to sit inside its own group. Chained loose onto the
     * query, a vehicle clause would widen the result rather than narrow it — and
     * the search would quietly return everything.
     */
    public function test_a_term_matching_nothing_returns_nothing(): void
    {
        $this->truck('Volvo FH 460', 'CVB 407');
        $this->route();
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași']);

        Livewire::test(RouteList::class)
            ->set('search', 'Vladivostok')
            ->assertSee('Nicio rută găsită')
            ->assertDontSee('Hâncești → Brăila')
            ->assertDontSee('Bălți → Iași');
    }

    public function test_it_pages_once_there_are_more_rows_than_fit(): void
    {
        foreach (range(1, 26) as $i) {
            $this->route(['origin' => 'Oraș '.$i]);
        }

        Livewire::test(RouteList::class)
            ->assertSee('Se afișează')
            ->assertSee('26')
            // Newest first, and the id breaks the tie between rows saved in the
            // same second — so page one is 26 down to 12, page two the rest.
            ->assertSee('Oraș 26 →')
            ->assertDontSee('Oraș 1 →')
            ->call('gotoPage', 2)
            ->assertSee('Oraș 1 →')
            ->assertDontSee('Oraș 26 →');
    }

    public function test_a_short_list_shows_no_pager(): void
    {
        foreach (range(1, 5) as $i) {
            $this->route(['origin' => 'Oraș '.$i]);
        }

        Livewire::test(RouteList::class)->assertDontSee('Se afișează');
    }

    /**
     * Narrowing the list while on a later page has to go back to the first, or the
     * result set no longer has the page being asked for.
     */
    public function test_searching_returns_to_the_first_page(): void
    {
        foreach (range(1, 26) as $i) {
            $this->route(['origin' => 'Oraș '.$i]);
        }

        Livewire::test(RouteList::class)
            ->call('gotoPage', 2)
            ->set('search', 'Oraș')
            ->assertSee('Se afișează')
            ->assertSee('Oraș 26 →');
    }
}
