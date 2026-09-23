<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Overview;
use App\Models\RouteCalculation;
use App\Models\Vehicle;
use App\Support\FleetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The charts on the summary screen: what the routes made month by month, what
 * each truck brought in, and what a year leaves behind once the trucks are paid
 * for.
 *
 * The per-route arithmetic is guarded elsewhere (RouteCostingTest); what is
 * checked here is the grouping on top of it — which bucket a route lands in,
 * which rows the fleet chart has, and above all that a truck's annual bills are
 * subtracted exactly once.
 */
class OverviewSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** Stands the clock still, so "last 12 months" means the same thing all run. */
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-08-15 12:00:00');
    }

    /**
     * A route that earns 10 000 lei and costs a known, round amount:
     *
     *     venit      500 km × ... → 25 t × 20 € × 20 lei = 10 000
     *     combustibil 500 km × 0,4 L × 30 lei            =  6 000
     *     salariu     o zi                               =  1 000
     *     uzură       500 km × 2 lei                     =  1 000
     *     profit                                         =  2 000
     */
    private function route(string $savedAt, ?Vehicle $vehicle = null, float $tonnes = 25): RouteCalculation
    {
        $route = RouteCalculation::create([
            'origin' => 'Hâncești',
            'destination' => 'Brăila',
            'vehicle_id' => $vehicle?->id,
            'distance_km' => 250,
            'return_distance_km' => 250,
            'tonnes' => $tonnes,
            'price_per_tonne' => 20,
            'eur_rate' => 20,
            'consumption' => 0.4,
            'fuel_price' => 30,
            'wear_per_km' => 2,
            'customs' => 0,
            'vignette' => 0,
            'other_costs' => 0,
            'days' => 1,
            'driver_first_day' => 1000,
            'driver_extra_day' => 0,
        ]);

        // The date axis is the moment the route was saved, and there is no other
        // date on the row to stand in for it.
        $route->forceFill(['created_at' => CarbonImmutable::parse($savedAt)])->save();

        return $route->fresh();
    }

    private function vehicle(string $name, float $annualKm = 100000, bool $active = true): Vehicle
    {
        return Vehicle::create([
            'name' => $name,
            'consumption' => 0.4,
            'insurance_annual' => 20000,
            'service_annual' => 20000,
            'tyres_annual' => 0,
            'other_annual' => 0,
            'gps_monthly' => 0,
            'annual_km' => $annualKm,
            'active' => $active,
        ]);
    }

    private function summary(): FleetSummary
    {
        return new FleetSummary(
            RouteCalculation::query()->with('vehicle')->latest()->get(),
            Vehicle::query()->orderBy('name')->get(),
            $this->now,
        );
    }

    public function test_the_months_are_bucketed_by_the_date_the_route_was_saved(): void
    {
        $this->route('2026-08-02');
        $this->route('2026-08-30');
        $this->route('2026-06-10');

        $series = $this->summary()->series('12');
        $buckets = collect($series['buckets'])->keyBy('key');

        $this->assertCount(12, $series['buckets']);
        $this->assertSame('2025-09', $series['buckets'][0]['key']);
        $this->assertSame('2026-08', $series['buckets'][11]['key']);

        $this->assertSame(2, $buckets['2026-08']['count']);
        $this->assertEqualsWithDelta(20000.0, $buckets['2026-08']['revenue'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $buckets['2026-08']['profit'], 0.001);

        $this->assertSame(1, $buckets['2026-06']['count']);

        // A month with no routes stays in the strip at zero: closing the gap
        // would make an idle July look like it never happened.
        $this->assertSame(0, $buckets['2026-07']['count']);
        $this->assertSame(0.0, $buckets['2026-07']['revenue']);
    }

    public function test_a_shorter_period_leaves_the_older_routes_out(): void
    {
        $this->route('2026-08-02');
        $this->route('2026-01-20');

        $this->assertSame(1, $this->summary()->series('6')['count']);
        $this->assertSame(2, $this->summary()->series('12')['count']);
        $this->assertSame(2, $this->summary()->series('all')['count']);
    }

    public function test_a_long_history_steps_up_from_months_to_quarters(): void
    {
        $this->route('2021-02-10');
        $this->route('2026-08-02');

        $series = $this->summary()->series('all');

        $this->assertSame('quarter', $series['granularity']);
        $this->assertSame('2021-T1', $series['buckets'][0]['key']);
        $this->assertSame('2026-T3', end($series['buckets'])['key']);
        $this->assertSame(2, $series['count']);
    }

    public function test_the_scale_follows_the_cost_of_a_month_that_lost_money(): void
    {
        // Two tonnes instead of 25: 800 lei of revenue against 8 000 of cost.
        $this->route('2026-08-02', tonnes: 2);

        $series = $this->summary()->series('6');
        $bucket = collect($series['buckets'])->firstWhere('key', '2026-08');

        $this->assertEqualsWithDelta(800.0, $bucket['revenue'], 0.001);
        $this->assertEqualsWithDelta(8000.0, $bucket['cost'], 0.001);
        $this->assertEqualsWithDelta(-7200.0, $bucket['profit'], 0.001);
        $this->assertEqualsWithDelta(8000.0, $series['max'], 0.001);
    }

    public function test_each_truck_gets_its_own_row_and_an_idle_one_is_listed_at_zero(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $this->vehicle('MAN TGX');

        $this->route('2026-08-02', $volvo);
        $this->route('2026-08-03', $volvo);
        $this->route('2026-08-04');

        $rows = collect($this->summary()->perVehicle('12'))->keyBy('label');

        $this->assertEqualsWithDelta(20000.0, $rows['Volvo FH']['revenue'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $rows['Volvo FH']['profit'], 0.001);
        $this->assertSame(2, $rows['Volvo FH']['routes']);
        $this->assertEqualsWithDelta(1000.0, $rows['Volvo FH']['distance'], 0.001);
        $this->assertEqualsWithDelta(20.0, $rows['Volvo FH']['margin'], 0.001);

        // A truck with no runs still bills its insurance, so it is on the chart.
        $this->assertSame(0, $rows['MAN TGX']['routes']);
        $this->assertSame(0.0, $rows['MAN TGX']['revenue']);
        $this->assertEqualsWithDelta(40000.0, $rows['MAN TGX']['fixed'], 0.001);

        // Routes saved without a truck are collected rather than dropped, so the
        // rows still add up to the totals above them.
        $this->assertSame(1, $rows['Fără vehicul']['routes']);

        $this->assertSame('Volvo FH', $this->summary()->perVehicle('12')[0]['label']);
    }

    public function test_an_inactive_truck_appears_only_if_it_actually_drove(): void
    {
        $retired = $this->vehicle('Volvo vechi', active: false);

        $this->assertSame([], $this->summary()->perVehicle('12'));

        $this->route('2026-08-02', $retired);

        $rows = $this->summary()->perVehicle('12');
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('inactiv', (string) $rows[0]['note']);
    }

    public function test_the_annual_result_subtracts_the_truck_bills_once_and_not_the_wear_again(): void
    {
        $volvo = $this->vehicle('Volvo FH');

        $this->route('2026-08-02', $volvo);
        $this->route('2026-03-02', $volvo);

        $annual = $this->summary()->annual();

        // 2 × 10 000 in, 2 × 7 000 of road costs (fuel + driver), so 6 000 left
        // before the truck's own bills — the wear is not among them.
        $this->assertEqualsWithDelta(20000.0, $annual['revenue'], 0.001);
        $this->assertEqualsWithDelta(14000.0, $annual['road_cost'], 0.001);
        $this->assertEqualsWithDelta(6000.0, $annual['contribution'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $annual['wear'], 0.001);

        // 40 000 of bills a year against 6 000 of contribution.
        $this->assertEqualsWithDelta(40000.0, $annual['fixed'], 0.001);
        $this->assertEqualsWithDelta(-34000.0, $annual['result'], 0.001);

        // Subtracting the wear as well would have landed on -36 000.
        $this->assertNotEqualsWithDelta(-36000.0, $annual['result'], 0.001);

        // 1 000 km driven at 2 lei recovered 2 000 of the 40 000 billed.
        $this->assertEqualsWithDelta(5.0, $annual['coverage'], 0.001);
    }

    public function test_a_year_that_is_not_full_yet_is_reported_as_a_rate(): void
    {
        $this->vehicle('Volvo FH');
        $this->route('2026-07-02');
        $this->route('2026-08-02');

        $annual = $this->summary()->annual();

        $this->assertTrue($annual['partial']);
        $this->assertSame(2, $annual['months']);
        // 6 000 of contribution over two months is 36 000 over twelve, less the
        // 40 000 the truck costs.
        $this->assertEqualsWithDelta(-4000.0, $annual['run_rate'], 0.001);
    }

    public function test_a_full_year_of_records_is_reported_as_it_is(): void
    {
        $this->vehicle('Volvo FH');
        $this->route('2025-01-02');
        $this->route('2026-08-02');

        $annual = $this->summary()->annual();

        // The route from 2025 is outside the twelve months and does not count.
        $this->assertFalse($annual['partial']);
        $this->assertSame(12, $annual['months']);
        $this->assertNull($annual['run_rate']);
        $this->assertSame(1, $annual['routes']);
    }

    public function test_an_empty_fleet_and_an_empty_table_produce_zeroes_rather_than_errors(): void
    {
        $summary = $this->summary();

        $this->assertSame(0, $summary->series('all')['count']);
        $this->assertSame(0.0, $summary->series('all')['max']);
        $this->assertSame([], $summary->perVehicle('12'));
        $this->assertSame(0.0, $summary->annual()['result']);
        $this->assertSame(0, $summary->annual()['months']);
        $this->assertNull($summary->annual()['run_rate']);
    }

    public function test_the_period_switch_only_accepts_the_windows_it_offers(): void
    {
        $this->assertSame('6', FleetSummary::period('6'));
        $this->assertSame('all', FleetSummary::period('all'));
        $this->assertSame('12', FleetSummary::period('nonsense'));
        $this->assertSame('12', FleetSummary::period(null));
    }

    public function test_a_month_that_lost_money_is_drawn_as_a_loss(): void
    {
        CarbonImmutable::setTestNow($this->now);

        // Two tonnes on a run that costs 8 000: the column is drawn to its cost,
        // with the uncovered part in red on top of it.
        $this->route('2026-08-02', tonnes: 2);

        Livewire::test(Overview::class)
            ->assertSeeHtml('bg-red-600/70')
            ->assertSee('Pierdere');

        CarbonImmutable::setTestNow();
    }

    public function test_a_window_with_no_routes_and_no_trucks_draws_an_empty_fleet_chart(): void
    {
        CarbonImmutable::setTestNow($this->now);

        // Saved three years ago, so the six-month window is empty — and with no
        // truck on file there is not even an idle row to draw.
        $this->route('2023-08-02');

        Livewire::test(Overview::class)
            ->call('setPeriod', '6')
            ->assertSee('Nicio mașină de arătat')
            ->call('setPeriod', 'all')
            ->assertSee('Fără vehicul');

        CarbonImmutable::setTestNow();
    }

    public function test_the_switch_narrows_the_charts_on_the_screen(): void
    {
        CarbonImmutable::setTestNow($this->now);

        $this->route('2026-08-02');
        $this->route('2026-01-20');

        Livewire::test(Overview::class)
            ->assertSet('period', '12')
            ->assertSeeHtml('12 luni')
            ->call('setPeriod', '6')
            ->assertSet('period', '6')
            ->call('setPeriod', 'orice')
            ->assertSet('period', '12');

        CarbonImmutable::setTestNow();
    }
}
