<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Vehicle;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    private function truck(array $overrides = []): Vehicle
    {
        return new Vehicle($overrides + [
            'name' => 'Volvo FH 460',
            'consumption' => 0.45,
            'insurance_annual' => 18000,
            'service_annual' => 24000,
            'tyres_annual' => 12000,
            'other_annual' => 0,
            'gps_monthly' => 400,
            'annual_km' => 60000,
        ]);
    }

    public function test_the_gps_subscription_is_annualised_rather_than_stored_twice(): void
    {
        $this->assertSame(4800.0, $this->truck()->gpsAnnual());
    }

    public function test_the_annual_total_gathers_every_cadence(): void
    {
        // 18 000 + 24 000 + 12 000 + 0 + (400 × 12).
        $this->assertSame(58800.0, $this->truck()->annualTotal());
        $this->assertSame(4900.0, $this->truck()->monthlyTotal());
    }

    /**
     * A truck's bills are a record of what the fleet costs, never an input to a
     * route: the wear a route is charged is the flat rate in Settings, and
     * nothing here divides into it.
     */
    public function test_a_truck_offers_no_per_kilometre_rate(): void
    {
        $this->assertFalse(
            method_exists($this->truck(), 'realCostPerKm'),
            'Uzura se reglează doar din Valori implicite; camionul nu mai derivă un tarif pe km.',
        );
    }

    public function test_the_expense_lines_keep_zeroes_and_flag_the_subscription(): void
    {
        $lines = collect($this->truck()->expenseLines())->keyBy('key');

        $this->assertSame(0.0, $lines['other']['annual']);
        $this->assertSame(4800.0, $lines['gps']['annual']);
        $this->assertSame('abonament lunar', $lines['gps']['note']);
    }
}
