<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Calculator;
use App\Livewire\Dashboard\Defaults;
use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Support\BnmRates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\CreatesFleet;
use Tests\TestCase;

/**
 * The euro and dollar rates, taken automatically from the National Bank of
 * Moldova.
 *
 * Every answer here is faked — the real feed is reached only by hand. The
 * fixture is a real BNM document, saved on 09.10.2026, so the parser is held to
 * the actual format rather than to one imagined from it.
 */
class BnmRatesTest extends TestCase
{
    use CreatesFleet;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 9 October 2026, mid-morning in Chișinău.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 10:00', BnmRates::TIMEZONE));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function realDocument(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/bnm-2026-10-09.xml'));
    }

    /** BNM answering with the real document, counting how often it is asked. */
    private function bnmAnswers(string $body, int $status = 200): void
    {
        Http::fake(['www.bnm.md/*' => Http::response($body, $status)]);
    }

    // -- The parser, against the real document -------------------------------

    public function test_it_reads_the_euro_and_the_dollar_out_of_a_real_document(): void
    {
        $rates = BnmRates::parse($this->realDocument());

        $this->assertSame(20.0179, $rates['EUR']);
        $this->assertSame(17.9003, $rates['USD']);
        $this->assertSame('09.10.2026', $rates['date']->format('d.m.Y'));
    }

    /** Some currencies are quoted per 10 units. The rate is per one. */
    public function test_it_divides_by_the_nominal(): void
    {
        $xml = '<?xml version="1.0"?><ValCurs Date="09.10.2026">'
            .'<Valute><CharCode>EUR</CharCode><Nominal>10</Nominal><Value>200.179</Value></Valute>'
            .'<Valute><CharCode>USD</CharCode><Nominal>1</Nominal><Value>17.9003</Value></Valute>'
            .'</ValCurs>';

        $this->assertSame(20.0179, BnmRates::parse($xml)['EUR']);
    }

    public function test_it_rejects_anything_that_is_not_a_rates_document(): void
    {
        $this->assertNull(BnmRates::parse(''));
        $this->assertNull(BnmRates::parse('<html><body>Not found</body></html>'));
        $this->assertNull(BnmRates::parse('<ValCurs Date="09.10'));

        // A document missing one of the two currencies is no use for either.
        $this->assertNull(BnmRates::parse(
            '<?xml version="1.0"?><ValCurs Date="09.10.2026"><Valute><CharCode>EUR</CharCode><Nominal>1</Nominal><Value>20</Value></Valute></ValCurs>'
        ));
    }

    /** An external entity must not be expanded into the document. */
    public function test_it_does_not_expand_external_entities(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>'
            .'<ValCurs Date="09.10.2026">'
            .'<Valute><CharCode>EUR</CharCode><Nominal>1</Nominal><Value>&e;</Value></Valute>'
            .'<Valute><CharCode>USD</CharCode><Nominal>1</Nominal><Value>17.9</Value></Valute>'
            .'</ValCurs>';

        $this->assertNull(BnmRates::parse($xml));
    }

    // -- Fetching -------------------------------------------------------------

    public function test_it_asks_for_chisinaus_today(): void
    {
        $this->bnmAnswers($this->realDocument());

        BnmRates::today();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'get_xml=1')
            && str_contains($request->url(), 'date=09.10.2026'));
    }

    /**
     * At 01:30 in Chișinău it is still yesterday in UTC — the date asked for has
     * to be Moldova's.
     */
    public function test_just_after_midnight_it_asks_for_the_new_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 01:30', BnmRates::TIMEZONE));
        $this->bnmAnswers(str_replace('09.10.2026', '10.10.2026', $this->realDocument()));

        BnmRates::today();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'date=10.10.2026'));
    }

    /** A published rate never changes, so it is asked for once. */
    public function test_a_published_rate_is_fetched_once(): void
    {
        $this->bnmAnswers($this->realDocument());

        BnmRates::today();
        BnmRates::today();
        BnmRates::today();

        Http::assertSentCount(1);
    }

    public function test_an_unpublished_day_comes_back_empty(): void
    {
        $this->bnmAnswers('', 404);

        $this->assertNull(BnmRates::today());
    }

    /**
     * A failure is remembered for a while, so an outage costs one slow request
     * rather than one on every page that asks.
     */
    public function test_a_failure_is_not_retried_straight_away(): void
    {
        $this->bnmAnswers('', 404);

        BnmRates::today();
        BnmRates::today();

        Http::assertSentCount(1);
    }

    public function test_an_unreachable_bank_comes_back_empty_rather_than_throwing(): void
    {
        Http::fake(['www.bnm.md/*' => fn () => throw new ConnectionException('timed out')]);

        $this->assertNull(BnmRates::today());
    }

    // -- Into the settings ----------------------------------------------------

    public function test_todays_rates_go_into_the_settings(): void
    {
        $this->bnmAnswers($this->realDocument());

        $this->assertTrue(BnmRates::refreshSettings());

        $this->assertSame(20.0179, Setting::quoteRate('EUR'));
        $this->assertSame(17.9003, Setting::quoteRate('USD'));
        $this->assertSame('bnm', Setting::get('rates_source'));
        $this->assertSame('2026-10-09', Setting::get('rates_date'));
    }

    public function test_settings_already_current_are_not_fetched_again(): void
    {
        $this->bnmAnswers($this->realDocument());

        BnmRates::refreshSettings();
        BnmRates::refreshSettings();

        Http::assertSentCount(1);
    }

    /** A correction typed in by hand holds for the day. */
    public function test_a_manual_rate_set_today_is_left_alone(): void
    {
        Setting::put(['eur_rate' => '20.5', 'rates_source' => 'manual', 'rates_date' => '2026-10-09']);
        $this->bnmAnswers($this->realDocument());

        BnmRates::refreshSettings();

        $this->assertSame(20.5, Setting::quoteRate('EUR'));
        Http::assertNothingSent();
    }

    /** …and BNM takes over again the next day. */
    public function test_a_manual_rate_from_yesterday_gives_way_to_bnm(): void
    {
        Setting::put(['eur_rate' => '20.5', 'rates_source' => 'manual', 'rates_date' => '2026-10-08']);
        $this->bnmAnswers($this->realDocument());

        BnmRates::refreshSettings();

        $this->assertSame(20.0179, Setting::quoteRate('EUR'));
        $this->assertSame('bnm', Setting::get('rates_source'));
    }

    public function test_when_bnm_is_down_the_last_known_rates_stay(): void
    {
        Setting::put(['eur_rate' => '19.95', 'usd_rate' => '17.80', 'rates_source' => 'bnm', 'rates_date' => '2026-10-08']);
        $this->bnmAnswers('', 404);

        $this->assertFalse(BnmRates::refreshSettings());

        $this->assertSame(19.95, Setting::quoteRate('EUR'));
        $this->assertSame(17.8, Setting::quoteRate('USD'));
    }

    // -- On the screens -------------------------------------------------------

    public function test_a_new_route_opens_on_todays_bnm_rate(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Calculator::class)
            ->assertSet('quote_rate', '20.0179')
            ->assertSee('Curs oficial BNM din 09.10.2026.')
            ->set('quote_currency', 'USD')
            ->assertSet('quote_rate', '17.9003');
    }

    public function test_a_rate_changed_on_the_route_says_so(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Calculator::class)
            ->set('quote_rate', '20.5')
            ->assertSee('Modificat pe această cursă. Cursul zilei: 20,0179.');
    }

    /**
     * A saved route keeps the rate it was quoted on. Opening it to edit must not
     * ask BNM, nor move its rate to today's.
     */
    public function test_editing_a_saved_route_keeps_its_rate_and_does_not_ask_bnm(): void
    {
        $route = RouteCalculation::create([
            'origin' => 'Hâncești', 'destination' => 'Brăila',
            'vehicle_id' => $this->spreadsheetTruck()->id,
            'distance_km' => 220, 'return_distance_km' => 220,
            'tonnes' => 25, 'price_per_tonne' => 35,
            'quote_currency' => 'EUR', 'quote_rate' => 19.9,
            'consumption' => 0.45, 'fuel_price' => 32, 'wear_per_km' => 2,
            'customs' => 700, 'vignette' => 600,
            'days' => 3, 'driver_first_day' => 2200, 'driver_extra_day' => 1000,
        ]);

        $this->bnmAnswers($this->realDocument());

        Livewire::test(Calculator::class, ['routeId' => $route->id])
            ->assertSet('quote_rate', '19.9')
            ->assertSee('Cursul cu care a fost salvată ruta.');

        Http::assertNothingSent();
    }

    /** With BNM down, the calculator still opens and still saves. */
    public function test_the_calculator_works_with_bnm_down(): void
    {
        $this->bnmAnswers('', 404);

        Livewire::test(Calculator::class)
            ->assertSet('quote_rate', '19.9')
            ->assertSee('BNM nu a răspuns')
            ->set('origin', 'Hâncești')
            ->set('destination', 'Brăila')
            ->set('vehicle_id', $this->spreadsheetTruck()->id)
            ->set('distance_km', '220')
            ->set('days', '3')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(19.9, RouteCalculation::sole()->quote_rate);
    }

    public function test_the_defaults_screen_opens_on_todays_rates(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Defaults::class)
            ->assertSet('eur_rate', '20.0179')
            ->assertSet('usd_rate', '17.9003')
            ->assertSee('Curs oficial BNM din');
    }

    /**
     * The screen saves every field at once. Changing the fuel price must not
     * switch the rates off BNM for the day.
     */
    public function test_saving_other_defaults_leaves_the_rates_on_bnm(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Defaults::class)
            ->set('fuel_price', '33')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('bnm', Setting::get('rates_source'));
    }

    public function test_changing_a_rate_by_hand_marks_it_manual(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Defaults::class)
            ->set('eur_rate', '20.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('manual', Setting::get('rates_source'));
        $this->assertSame(20.5, Setting::quoteRate('EUR'));
    }

    public function test_the_button_takes_the_official_rates_back(): void
    {
        $this->bnmAnswers($this->realDocument());

        Livewire::test(Defaults::class)
            ->set('eur_rate', '20.5')
            ->call('save')
            ->call('refreshFromBnm')
            ->assertSet('eur_rate', '20.0179');

        $this->assertSame('bnm', Setting::get('rates_source'));
    }

    public function test_the_command_fetches_todays_rates(): void
    {
        $this->bnmAnswers($this->realDocument());

        $this->artisan('rates:bnm')
            ->expectsOutputToContain('EUR 20.0179, USD 17.9003')
            ->assertSuccessful();
    }

    public function test_the_command_reports_an_outage(): void
    {
        $this->bnmAnswers('', 404);

        $this->artisan('rates:bnm')
            ->expectsOutputToContain('BNM nu a răspuns')
            ->assertFailed();
    }
}
