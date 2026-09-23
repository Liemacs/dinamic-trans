<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Vehicle;

/**
 * A route cannot be saved without a truck, so every test that saves one needs a
 * truck to hand.
 */
trait CreatesFleet
{
    /**
     * A truck tuned to the source spreadsheet's own parameters: 0.45 L/km, and
     * annual bills that come to exactly 2 lei/km over its year's distance
     * (120 000 ÷ 60 000). A route run on it therefore reproduces the
     * spreadsheet's figures rather than shifting them by the truck's own rates.
     */
    protected function spreadsheetTruck(): Vehicle
    {
        return Vehicle::create([
            'name' => 'Volvo FH 460',
            'plate' => 'ABC 123',
            'consumption' => 0.45,
            'insurance_annual' => 60000,
            'service_annual' => 40000,
            'tyres_annual' => 15200,
            'other_annual' => 0,
            'gps_monthly' => 400,   // 4 800 a year
            'annual_km' => 60000,
        ]);
    }
}
