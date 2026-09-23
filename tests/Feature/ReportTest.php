<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\Report;
use App\Models\RouteCalculation;
use App\Models\Vehicle;
use App\Support\PeriodReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The darea de seamă: the routes of a period on one sheet, and what the period
 * leaves behind once the trucks are paid for.
 *
 * Two things are guarded here above all. The period must hold exactly the routes
 * inside it — no neighbour's month leaking in through a date boundary. And the
 * trucks' annual bills must be charged once, pro-rated to the length of the
 * period: a report on one month carries one month of insurance, not a year of
 * it, and not a year of it twice.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-08-15 12:00:00');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** 10 000 in, 8 000 out — of which 1 000 is wear. Profit 2 000. */
    private function route(string $savedAt, ?Vehicle $vehicle = null): RouteCalculation
    {
        $route = RouteCalculation::create([
            'origin' => 'Hâncești',
            'destination' => 'Brăila',
            'vehicle_id' => $vehicle?->id,
            'distance_km' => 250,
            'return_distance_km' => 250,
            'tonnes' => 25,
            'price_per_tonne' => 20,
            'eur_rate' => 20,
            'consumption' => 0.4,
            'fuel_price' => 30,
            'wear_per_km' => 2,
            'days' => 1,
            'driver_first_day' => 1000,
            'driver_extra_day' => 0,
        ]);

        $route->forceFill(['created_at' => CarbonImmutable::parse($savedAt)])->save();

        return $route->fresh();
    }

    private function vehicle(string $name, bool $active = true): Vehicle
    {
        return Vehicle::create([
            'name' => $name,
            'plate' => 'ABC 123',
            'consumption' => 0.4,
            'insurance_annual' => 36500,   // 100 a day in a normal year
            'service_annual' => 0,
            'tyres_annual' => 0,
            'other_annual' => 0,
            'gps_monthly' => 0,
            'annual_km' => 100000,
            'active' => $active,
        ]);
    }

    public function test_the_screen_opens_on_the_current_month(): void
    {
        Livewire::test(Report::class)
            ->assertSet('from', '2026-08-01')
            ->assertSet('to', '2026-08-31')
            ->assertSee('Luna curentă');
    }

    public function test_every_preset_lands_on_the_range_it_names(): void
    {
        $expected = [
            'month' => ['2026-08-01', '2026-08-31'],
            'prev_month' => ['2026-07-01', '2026-07-31'],
            'year' => ['2026-01-01', '2026-12-31'],
            'prev_year' => ['2025-01-01', '2025-12-31'],
            'last12' => ['2025-09-01', '2026-08-31'],
        ];

        // The order matters as much as the ranges: the month leads the row and
        // the screen opens on it.
        $this->assertSame(array_keys($expected), array_keys(PeriodReport::PRESETS));

        foreach ($expected as $preset => [$from, $to]) {
            Livewire::test(Report::class)
                ->call('preset', $preset)
                ->assertSet('from', $from)
                ->assertSet('to', $to);
        }
    }

    public function test_only_the_routes_inside_the_period_are_on_the_sheet(): void
    {
        $this->route('2026-07-31 23:59:59');
        $this->route('2026-08-01 00:00:01');
        $this->route('2026-08-31 23:59:59');
        $this->route('2026-09-01 00:00:01');

        [$from, $to] = PeriodReport::range('month', $this->now);
        $report = PeriodReport::for($from, $to);

        // The boundaries belong to the month they fall in, both of them.
        $this->assertSame(2, $report->totals()['routes']);
        $this->assertEqualsWithDelta(20000.0, $report->totals()['revenue'], 0.001);
    }

    public function test_the_truck_filter_narrows_both_the_routes_and_the_bills(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $man = $this->vehicle('MAN TGX');

        $this->route('2026-03-02', $volvo);
        $this->route('2026-03-03', $man);

        [$from, $to] = PeriodReport::range('year', $this->now);

        $fleet = PeriodReport::for($from, $to);
        $this->assertSame(2, $fleet->totals()['routes']);
        $this->assertEqualsWithDelta(73000.0, $fleet->bottomLine()['fixed_annual'], 0.001);

        $one = PeriodReport::for($from, $to, $volvo);
        $this->assertSame(1, $one->totals()['routes']);
        $this->assertEqualsWithDelta(36500.0, $one->bottomLine()['fixed_annual'], 0.001);
    }

    public function test_the_truck_bills_are_charged_by_the_day_of_the_period(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $this->route('2026-08-02', $volvo);

        [$from, $to] = PeriodReport::range('month', $this->now);
        $august = PeriodReport::for($from, $to, $volvo);

        // 31 days of a 36 500 a year truck is 3 100, not 36 500.
        $this->assertSame(31, $august->days());
        $this->assertEqualsWithDelta(3100.0, $august->bottomLine()['fixed'], 0.001);

        [$from, $to] = PeriodReport::range('year', $this->now);
        $year = PeriodReport::for($from, $to, $volvo);

        $this->assertSame(365, $year->days());
        $this->assertEqualsWithDelta(36500.0, $year->bottomLine()['fixed'], 0.001);
    }

    public function test_the_result_does_not_subtract_the_wear_twice(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $this->route('2026-08-02', $volvo);

        [$from, $to] = PeriodReport::range('month', $this->now);
        $line = PeriodReport::for($from, $to, $volvo)->bottomLine();

        // 10 000 in; 7 000 of road costs, wear left out of them.
        $this->assertEqualsWithDelta(10000.0, $line['revenue'], 0.001);
        $this->assertEqualsWithDelta(7000.0, $line['road_cost'], 0.001);
        $this->assertEqualsWithDelta(3000.0, $line['contribution'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $line['wear'], 0.001);

        // 3 000 − 3 100 of August insurance. Subtracting the wear as well would
        // have landed on -1 100.
        $this->assertEqualsWithDelta(-100.0, $line['result'], 0.001);
    }

    public function test_a_reversed_or_broken_range_is_repaired_rather_than_refused(): void
    {
        [$from, $to] = PeriodReport::period('2026-08-31', '2026-08-01', $this->now);
        $this->assertSame('2026-08-01', $from->toDateString());
        $this->assertSame('2026-08-31', $to->toDateString());

        // Nothing usable in the URL: the current month, as if it had been empty.
        [$from, $to] = PeriodReport::period('nu-i o dată', null, $this->now);
        $this->assertSame('2026-08-01', $from->toDateString());
        $this->assertSame('2026-08-31', $to->toDateString());
    }

    public function test_the_totals_row_adds_up_the_lines_above_it(): void
    {
        $this->route('2026-08-02');
        $this->route('2026-08-03');

        [$from, $to] = PeriodReport::range('month', $this->now);
        $report = PeriodReport::for($from, $to);
        $totals = $report->totals();

        $rows = $report->rows();
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(50.0, $totals['tonnes'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $totals['distance'], 0.001);
        $this->assertEqualsWithDelta(400.0, $totals['litres'], 0.001);
        $this->assertEqualsWithDelta(20000.0, $totals['revenue'], 0.001);
        $this->assertEqualsWithDelta(16000.0, $totals['cost'], 0.001);
        $this->assertEqualsWithDelta(4000.0, $totals['profit'], 0.001);
        $this->assertEqualsWithDelta(20.0, $totals['margin'], 0.001);
    }

    public function test_the_printable_page_shows_the_same_period_as_the_screen(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $this->route('2026-08-02', $volvo);
        $this->route('2026-02-02');

        $this->get(route('dashboard.report.print', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Darea de seamă')
            ->assertSee('01.08.2026 – 31.08.2026')
            ->assertSee('Hâncești → Brăila')
            ->assertSee('toată flota');

        // Asked about one truck, the sheet says so in the header instead of
        // repeating the name on every line.
        $this->get(route('dashboard.report.print', ['from' => '2026-08-01', 'to' => '2026-08-31', 'v' => $volvo->id]))
            ->assertOk()
            ->assertSee('Volvo FH · ABC 123')
            ->assertDontSee('toată flota');
    }

    public function test_the_printable_page_survives_a_truck_that_no_longer_exists(): void
    {
        $this->route('2026-08-02');

        $this->get(route('dashboard.report.print', ['from' => '2026-08-01', 'to' => '2026-08-31', 'v' => 999]))
            ->assertOk()
            ->assertSee('toată flota');
    }

    public function test_an_empty_period_prints_a_sheet_that_says_so(): void
    {
        $this->get(route('dashboard.report.print', ['from' => '2020-01-01', 'to' => '2020-12-31']))
            ->assertOk()
            ->assertSee('Nicio rută salvată în perioada aceasta.');
    }

    /** The sheet XML out of a downloaded workbook. */
    private function sheetXml(string $xlsx): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'report');
        file_put_contents($path, $xlsx);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Exportul nu este un fișier Excel valid.');

        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }

    public function test_the_export_hands_back_a_spreadsheet_named_after_the_period(): void
    {
        $this->route('2026-08-02');

        $response = $this->get(route('dashboard.report.excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="darea-de-seama_2026-08-01_2026-08-31.xlsx"');

        $xml = $this->sheetXml($response->getContent());

        $this->assertStringContainsString('Hâncești → Brăila', $xml);
        // The money is a number, not a formatted string — that is the point of
        // sending a workbook rather than a CSV.
        $this->assertStringContainsString('<v>10000</v>', $xml);
        $this->assertStringContainsString('DAREA DE SEAMĂ', $xml);
        $this->assertStringContainsString('Total', $xml);
        $this->assertStringContainsString('REZULTATUL PERIOADEI', $xml);
    }

    public function test_the_export_follows_the_truck_filter(): void
    {
        $volvo = $this->vehicle('Volvo FH');
        $this->route('2026-08-02', $volvo);
        $this->route('2026-08-03');

        $response = $this->get(route('dashboard.report.excel', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'v' => $volvo->id,
        ]));

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="darea-de-seama_volvo-fh_2026-08-01_2026-08-31.xlsx"');

        $xml = $this->sheetXml($response->getContent());

        // One truck named in the header, so no column repeating it — the last
        // column of the header row is the margin, one letter earlier than on a
        // fleet-wide sheet.
        $this->assertStringContainsString('Volvo FH · ABC 123', $xml);
        $this->assertStringContainsString('<c r="J4"', $xml);
        $this->assertStringNotContainsString('<c r="K4"', $xml);
    }

    public function test_an_empty_period_still_exports_a_readable_sheet(): void
    {
        $response = $this->get(route('dashboard.report.excel', ['from' => '2020-01-01', 'to' => '2020-12-31']));

        $response->assertOk();

        $xml = $this->sheetXml($response->getContent());

        $this->assertStringContainsString('DAREA DE SEAMĂ', $xml);
        $this->assertStringContainsString('Total', $xml);
    }

    public function test_the_screen_offers_both_ways_out(): void
    {
        $this->get(route('dashboard.report'))
            ->assertOk()
            ->assertSee('Excel')
            ->assertSee('Tipărește / PDF')
            ->assertSee(e(route('dashboard.report.excel', ['from' => '2026-08-01', 'to' => '2026-08-31'])), escape: false);
    }

    public function test_the_summary_links_to_the_report_for_the_year_it_reports_on(): void
    {
        $this->route('2026-08-02');

        $this->get(route('dashboard.overview'))
            ->assertOk()
            ->assertSee('Raport anual')
            // e(): the href is HTML-escaped in the page, ampersands included.
            ->assertSee(e(route('dashboard.report', ['from' => '2025-09-01', 'to' => '2026-08-31'])), escape: false);
    }
}
