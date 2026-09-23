<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RouteCosting;
use PHPUnit\Framework\TestCase;

class RouteCostingTest extends TestCase
{
    /**
     * Row 2 of the source spreadsheet (Calculator_rute.xlsx, sheet "Rute"):
     * Hâncești → Brăila, the worked example every figure below is checked
     * against.
     */
    private function hancestiBraila(): RouteCosting
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
        );
    }

    public function test_it_reproduces_the_spreadsheet_row(): void
    {
        $costing = $this->hancestiBraila();

        $this->assertSame(875.0, round($costing->revenueEur(), 2));      // F2
        $this->assertSame(17412.5, round($costing->revenue(), 2));       // H2
        $this->assertSame(198.0, round($costing->fuelLitres(), 2));      // I2
        $this->assertSame(6336.0, round($costing->fuelCost(), 2));       // K2
        $this->assertSame(4200.0, round($costing->driverSalary(), 2));   // L2
        $this->assertSame(880.0, round($costing->wearCost(), 2));        // N2
        $this->assertSame(4696.5, round($costing->profit(), 2));         // Q2
    }

    public function test_the_first_day_is_paid_at_its_own_rate(): void
    {
        $rates = ['days' => 0, 'driver_first_day' => 2200, 'driver_extra_day' => 1000,
            'distance_km' => 1, 'consumption' => 0, 'fuel_price' => 0];

        // Nobody on the road yet: no salary, rather than a phantom first day.
        $this->assertSame(0.0, RouteCosting::fromArray($rates)->driverSalary());

        // One day is the first day alone — not the first day plus a day.
        $this->assertSame(2200.0, RouteCosting::fromArray(['days' => 1] + $rates)->driverSalary());
        $this->assertSame(3200.0, RouteCosting::fromArray(['days' => 2] + $rates)->driverSalary());
        $this->assertSame(6200.0, RouteCosting::fromArray(['days' => 5] + $rates)->driverSalary());
    }

    public function test_consumption_is_litres_per_kilometre_not_per_hundred(): void
    {
        $costing = new RouteCosting(distanceKm: 100, consumption: 0.45, fuelPrice: 32);

        $this->assertSame(45.0, round($costing->fuelLitres(), 2));
    }

    public function test_revenue_is_converted_at_the_rate_on_the_route(): void
    {
        $costing = new RouteCosting(
            distanceKm: 100, consumption: 0, fuelPrice: 0,
            tonnes: 10, pricePerTonne: 50, eurRate: 20,
        );

        $this->assertSame(500.0, $costing->revenueEur());
        $this->assertSame(10000.0, $costing->revenue());
    }

    public function test_a_route_with_no_revenue_has_no_margin_rather_than_minus_one_hundred(): void
    {
        $costing = new RouteCosting(distanceKm: 100, consumption: 0.45, fuelPrice: 32);

        $this->assertSame(0.0, $costing->margin());
    }

    public function test_a_route_with_no_distance_divides_by_nothing(): void
    {
        $costing = new RouteCosting(distanceKm: 0, consumption: 0.45, fuelPrice: 32, tonnes: 10, pricePerTonne: 50, eurRate: 20);

        $this->assertFalse($costing->isComplete());
        $this->assertSame(0.0, $costing->costPerKm());
        $this->assertSame(0.0, $costing->profitPerKm());
        $this->assertSame(0.0, $costing->revenuePerKm());
    }

    public function test_break_even_per_tonne_is_quoted_back_in_euro(): void
    {
        $costing = $this->hancestiBraila();

        // 12 716 MDL of cost ÷ 19.9 ÷ 25 t.
        $this->assertSame(12716.0, round($costing->totalCost(), 2));
        $this->assertSame(25.56, round($costing->breakEvenPerTonneEur(), 2));

        // And it is genuinely the break-even: quote that and the profit is nil.
        $atBreakEven = new RouteCosting(
            distanceKm: 440, consumption: 0.45, fuelPrice: 32,
            tonnes: 25, pricePerTonne: $costing->breakEvenPerTonneEur(), eurRate: 19.9,
            wearPerKm: 2, customs: 700, vignette: 600,
            days: 3, driverFirstDay: 2200, driverExtraDay: 1000,
        );

        $this->assertSame(0.0, round($atBreakEven->profit(), 6));
    }

    public function test_break_even_per_tonne_needs_a_load_to_divide_by(): void
    {
        $costing = new RouteCosting(distanceKm: 440, consumption: 0.45, fuelPrice: 32, eurRate: 19.9);

        $this->assertSame(0.0, $costing->breakEvenPerTonneEur());
    }

    public function test_from_array_treats_blank_and_junk_input_as_zero(): void
    {
        $costing = RouteCosting::fromArray([
            'distance_km' => '440',
            'consumption' => '0.45',
            'fuel_price' => '32',
            'customs' => '',
            'vignette' => null,
            // 'tonnes' missing entirely
        ]);

        $this->assertSame(0.0, $costing->customs);
        $this->assertSame(0.0, $costing->vignette);
        $this->assertSame(0.0, $costing->tonnes);
        $this->assertSame(440.0, $costing->distanceKm);
    }

    public function test_the_breakdown_drops_empty_lines_and_sorts_by_size(): void
    {
        $breakdown = $this->hancestiBraila()->breakdown();

        // Fuel 6336, salary 4200, customs 700, vignette 600, wear 880 — no
        // "other costs" line, because nothing was entered for it.
        $this->assertSame(['fuel', 'salary', 'wear', 'customs', 'vignette'], array_column($breakdown, 'key'));
        $this->assertSame(100.0, round(array_sum(array_column($breakdown, 'share')), 6));
    }
}
