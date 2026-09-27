<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Livewire\Dashboard\Defaults;
use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Support\RouteCosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\CreatesFleet;
use Tests\TestCase;

/**
 * Some customers pay in dollars, so a route records the currency it was quoted
 * in alongside the rate that settled it.
 *
 * Costs and results stay in lei throughout — only the price changes currency.
 */
class QuoteCurrencyTest extends TestCase
{
    use CreatesFleet;
    use RefreshDatabase;

    private function quote(string $currency, float $rate): RouteCosting
    {
        return new RouteCosting(
            distanceKm: 220, consumption: 0.45, fuelPrice: 32,
            tonnes: 25, pricePerTonne: 35,
            quoteRate: $rate, quoteCurrency: $currency,
            wearPerKm: 2, customs: 700, vignette: 600,
            days: 3, driverFirstDay: 2200, driverExtraDay: 1000,
            returnDistanceKm: 220,
        );
    }

    public function test_the_same_price_in_dollars_converts_at_the_dollar_rate(): void
    {
        $eur = $this->quote('EUR', 19.9);
        $usd = $this->quote('USD', 17.5);

        // The quoted figure is the same 875; only what it settles to differs.
        $this->assertSame(875.0, round($eur->revenueQuoted(), 2));
        $this->assertSame(875.0, round($usd->revenueQuoted(), 2));

        $this->assertSame(17412.5, round($eur->revenue(), 2));
        $this->assertSame(15312.5, round($usd->revenue(), 2));

        // Costs are in lei and do not move with the customer's currency.
        $this->assertSame($eur->totalCost(), $usd->totalCost());
        $this->assertSame(4696.5, round($eur->profit(), 2));
        $this->assertSame(2596.5, round($usd->profit(), 2));
    }

    public function test_break_even_per_tonne_comes_back_in_the_quote_currency(): void
    {
        // 12 716 lei of cost, divided by the rate and the tonnes.
        $this->assertSame(25.56, round($this->quote('EUR', 19.9)->breakEvenPerTonneQuoted(), 2));
        $this->assertSame(29.07, round($this->quote('USD', 17.5)->breakEvenPerTonneQuoted(), 2));
    }

    public function test_an_unknown_currency_falls_back_to_euro(): void
    {
        $costing = RouteCosting::fromArray(['distance_km' => 100, 'quote_currency' => '']);

        $this->assertSame('EUR', $costing->quoteCurrency);
    }

    public function test_choosing_a_currency_brings_its_rate(): void
    {
        Livewire::test(Calculator::class)
            ->assertSet('quote_currency', 'EUR')
            ->assertSet('quote_rate', '19.9')
            ->set('quote_currency', 'USD')
            ->assertSet('quote_rate', '17.5')
            ->set('quote_currency', 'EUR')
            ->assertSet('quote_rate', '19.9');
    }

    /** The settings hold the usual rate, not the one on today's invoice. */
    public function test_the_rate_stays_editable_after_the_currency_is_chosen(): void
    {
        Livewire::test(Calculator::class)
            ->set('quote_currency', 'USD')
            ->set('quote_rate', '18.2')
            ->assertSet('quote_rate', '18.2');
    }

    public function test_a_route_in_dollars_saves_and_reads_back_in_dollars(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '220')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->set('quote_currency', 'USD')
            ->call('save')
            ->assertHasNoErrors();

        $costing = RouteCalculation::sole()->costing();

        $this->assertSame('USD', $costing->quoteCurrency);
        $this->assertSame(17.5, $costing->quoteRate);
        $this->assertSame(15312.5, round($costing->revenue(), 2));
    }

    /**
     * The currency is frozen with everything else: moving the rates afterwards
     * must not re-price a route already quoted.
     */
    public function test_a_saved_route_keeps_its_currency_and_rate(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '220')
            ->set('tonnes', '25')
            ->set('price_per_tonne', '35')
            ->set('days', '3')
            ->set('quote_currency', 'USD')
            ->call('save')
            ->assertHasNoErrors();

        $before = round(RouteCalculation::sole()->costing()->profit(), 2);

        Setting::put(['usd_rate' => '30', 'eur_rate' => '30', 'quote_currency' => 'EUR']);

        $fresh = RouteCalculation::sole()->costing();

        $this->assertSame('USD', $fresh->quoteCurrency);
        $this->assertSame(17.5, $fresh->quoteRate);
        $this->assertSame($before, round($fresh->profit(), 2));
    }

    public function test_a_currency_that_is_not_offered_is_refused(): void
    {
        Livewire::test(Calculator::class)
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '220')
            ->set('quote_currency', 'GBP')
            ->call('save')
            ->assertHasErrors(['quote_currency']);

        $this->assertSame(0, RouteCalculation::count());
    }

    public function test_the_defaults_screen_holds_both_rates(): void
    {
        Livewire::test(Defaults::class)
            ->assertSet('eur_rate', '19.9')
            ->assertSet('usd_rate', '17.5')
            ->set('usd_rate', '18.4')
            ->set('quote_currency', 'USD')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(18.4, Setting::quoteRate('USD'));
        $this->assertSame('USD', Setting::get('quote_currency'));

        // And a new route starts on it.
        Livewire::test(Calculator::class)
            ->assertSet('quote_currency', 'USD')
            ->assertSet('quote_rate', '18.4');
    }

    public function test_routes_of_both_currencies_still_total_in_lei(): void
    {
        $truck = $this->spreadsheetTruck();

        foreach ([['EUR', 35], ['USD', 35]] as [$currency, $price]) {
            Livewire::test(Calculator::class)
                ->set('origin', 'Hâncești')
                ->set('destination', 'Brăila')
                ->set('vehicle_id', $truck->id)
                ->set('distance_km', '220')
                ->set('tonnes', '25')
                ->set('price_per_tonne', (string) $price)
                ->set('days', '3')
                ->set('quote_currency', $currency)
                ->call('save')
                ->assertHasNoErrors();
        }

        // Summing across currencies is safe because revenue() is always lei.
        $total = RouteCalculation::all()->sum(fn (RouteCalculation $r): float => $r->costing()->revenue());

        $this->assertSame(32725.0, round($total, 2));
        $this->assertSame(['EUR', 'USD'], RouteCalculation::query()->orderBy('quote_currency')->pluck('quote_currency')->all());
    }
}
