<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RouteCosting;
use PHPUnit\Framework\TestCase;

/**
 * The return load: the truck detours to a third point, picks up someone else's
 * cargo and carries it home.
 *
 * Built on the spreadsheet's Hâncești → Brăila run, so every figure can be read
 * against the empty-return version of the same route.
 */
class ReturnLoadTest extends TestCase
{
    private function route(array $returnLeg = []): RouteCosting
    {
        return new RouteCosting(
            distanceKm: 440,
            consumption: 0.45,
            fuelPrice: 32,
            tonnes: 25,
            pricePerTonne: 35,
            eurRate: 19.9,
            wearPerKm: 2,
            customs: 700,
            vignette: 600,
            days: 3,
            driverFirstDay: 2200,
            driverExtraDay: 1000,
            returnDistanceKm: $returnLeg['km'] ?? 0.0,
            returnTonnes: $returnLeg['tonnes'] ?? 0.0,
            returnPricePerTonne: $returnLeg['price'] ?? 0.0,
            loadingDayBonus: $returnLeg['bonus'] ?? 0.0,
        );
    }

    /** Galați and back: 160 km, 20 t at 30 €, the 1 200 loading day. */
    private function withReturn(): RouteCosting
    {
        return $this->route(['km' => 160, 'tonnes' => 20, 'price' => 30, 'bonus' => 1200]);
    }

    public function test_an_empty_return_changes_nothing(): void
    {
        $costing = $this->route();

        $this->assertFalse($costing->hasReturnLoad());
        $this->assertSame(440.0, $costing->totalDistanceKm());
        $this->assertSame(4200.0, round($costing->driverSalary(), 2));
        $this->assertSame(4696.5, round($costing->profit(), 2));
    }

    /**
     * Driving home is not carrying a load home. Every route has a way back, and
     * treating those kilometres as a return load would mark them all as earning
     * on the way.
     */
    public function test_kilometres_home_alone_are_not_a_return_load(): void
    {
        $costing = $this->route(['km' => 440]);

        $this->assertFalse($costing->hasReturnLoad());
        $this->assertSame(880.0, $costing->totalDistanceKm());
        $this->assertSame(0.0, $costing->returnRevenueEur());

        // But they are charged: 440 extra km of fuel and wear.
        $this->assertSame(396.0, round($costing->fuelLitres(), 2));
        $this->assertSame(-2519.5, round($costing->profit(), 2));
    }

    public function test_the_detour_is_added_to_the_distance_every_cost_is_measured_against(): void
    {
        $costing = $this->withReturn();

        $this->assertTrue($costing->hasReturnLoad());
        $this->assertSame(600.0, $costing->totalDistanceKm());          // 440 + 160

        // Fuel and wear both run on the full 600 km, not the outbound 440.
        $this->assertSame(270.0, round($costing->fuelLitres(), 2));     // 600 × 0.45
        $this->assertSame(8640.0, round($costing->fuelCost(), 2));      // 270 × 32
        $this->assertSame(1200.0, round($costing->wearCost(), 2));      // 600 × 2
    }

    public function test_both_loads_are_priced_and_converted_together(): void
    {
        $costing = $this->withReturn();

        $this->assertSame(875.0, round($costing->outboundRevenueEur(), 2));  // 25 × 35
        $this->assertSame(600.0, round($costing->returnRevenueEur(), 2));    // 20 × 30
        $this->assertSame(1475.0, round($costing->revenueEur(), 2));
        $this->assertSame(29352.5, round($costing->revenue(), 2));           // × 19.9
        $this->assertSame(45.0, round($costing->totalTonnes(), 2));
    }

    /**
     * The loading day is paid on top of the day rate, not instead of it — the
     * driver still spent that day on the road.
     */
    public function test_the_loading_day_is_added_to_the_day_rate(): void
    {
        $costing = $this->withReturn();

        $this->assertSame(4200.0, round($costing->driverDaysPay(), 2));   // 2200 + 2 × 1000
        $this->assertSame(1200.0, round($costing->loadingDayBonus, 2));
        $this->assertSame(5400.0, round($costing->driverSalary(), 2));
    }

    public function test_the_whole_run_adds_up(): void
    {
        $costing = $this->withReturn();

        // 8 640 fuel + 5 400 driver + 1 200 wear + 700 customs + 600 vignette.
        $this->assertSame(16540.0, round($costing->totalCost(), 2));
        $this->assertSame(12812.5, round($costing->profit(), 2));
        $this->assertSame(43.65, round($costing->margin(), 2));

        // Per-kilometre rates divide by the full 600 km.
        $this->assertSame(21.35, round($costing->profitPerKm(), 2));
    }

    /**
     * A return load is worth taking here: it costs 3 824 more (fuel, wear, the
     * loading day) and brings 11 940 in, so the run earns 8 116 more than empty.
     */
    public function test_a_return_load_is_measured_against_coming_home_empty(): void
    {
        $empty = $this->route();
        $loaded = $this->withReturn();

        $this->assertSame(3824.0, round($loaded->totalCost() - $empty->totalCost(), 2));
        $this->assertSame(11940.0, round($loaded->revenue() - $empty->revenue(), 2));
        $this->assertSame(8116.0, round($loaded->profit() - $empty->profit(), 2));
    }

    public function test_break_even_per_tonne_spans_both_loads(): void
    {
        $costing = $this->withReturn();

        // 16 540 lei ÷ 19.9 ÷ 45 t.
        $this->assertSame(18.47, round($costing->breakEvenPerTonneEur(), 2));
    }

    public function test_the_breakdown_names_the_loading_day_separately(): void
    {
        $breakdown = $this->withReturn()->breakdown();
        $keys = array_column($breakdown, 'key');

        // A line of its own rather than folded into the salary, so the chart
        // shows what the return leg actually cost.
        $this->assertContains('loading', $keys);
        $this->assertEqualsCanonicalizing(
            ['fuel', 'salary', 'wear', 'loading', 'customs', 'vignette'],
            $keys,
        );

        // Ordering is by size, largest first. Asserted as a property rather than
        // as a fixed list: wear and the loading day both come to 1 200 here, and
        // pinning a tie would be testing PHP's sort, not this code.
        $amounts = array_column($breakdown, 'amount');
        $sorted = $amounts;
        rsort($sorted);
        $this->assertSame($sorted, $amounts);

        // And it stays out of the chart on a route that comes home empty.
        $this->assertNotContains('loading', array_column($this->route()->breakdown(), 'key'));
    }

    public function test_from_array_reads_the_return_leg(): void
    {
        $costing = RouteCosting::fromArray([
            'distance_km' => '440',
            'consumption' => '0.45',
            'fuel_price' => '32',
            'return_distance_km' => '160',
            'return_tonnes' => '20',
            'return_price_per_tonne' => '30',
            'loading_day_bonus' => '1200',
        ]);

        $this->assertSame(600.0, $costing->totalDistanceKm());
        $this->assertSame(1200.0, $costing->loadingDayBonus);
    }
}
