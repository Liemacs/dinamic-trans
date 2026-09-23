<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RouteCalculation;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The saved routes as a spreadsheet: the same rows the list is showing, filtered
 * the same way, with the figures stored as numbers rather than as formatted text.
 */
class RouteExportTest extends TestCase
{
    use RefreshDatabase;

    private function truck(string $name, string $plate): Vehicle
    {
        return Vehicle::create(['name' => $name, 'plate' => $plate, 'consumption' => 0.45]);
    }

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

    /** The sheet's XML, so the assertions can look at what is actually stored. */
    private function sheetXml(string $body): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $body);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file) === true, 'Fișierul descărcat nu este un .xlsx valid.');

        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($file);

        return $xml.$shared;
    }

    public function test_it_downloads_a_spreadsheet(): void
    {
        $this->route();

        $response = $this->get(route('dashboard.routes.excel'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('rute-salvate_', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_it_carries_every_route_and_its_figures(): void
    {
        $volvo = $this->truck('Volvo FH 460', 'CVB 407');
        $this->route(['vehicle_id' => $volvo->id]);

        $xml = $this->sheetXml($this->get(route('dashboard.routes.excel'))->getContent());

        $this->assertStringContainsString('Hâncești → Brăila', $xml);
        $this->assertStringContainsString('Volvo FH 460', $xml);
        $this->assertStringContainsString('CVB 407', $xml);

        // Numbers as numbers, not as "4.696,50" text a foreign Excel cannot sum.
        $this->assertStringContainsString('<v>4696.5</v>', $xml);
        $this->assertStringContainsString('<v>440</v>', $xml);   // distanța totală
        $this->assertStringContainsString('<v>198</v>', $xml);   // litri

        /*
         * The margin, as the fraction a percent-formatted cell holds. XlsxWriter
         * divides a PERCENT value by 100 itself, and passing it one already
         * divided showed 27% as 0% — a figure wrong by two orders of magnitude
         * that still looked like a number.
         */
        $this->assertStringContainsString('<v>0.2697', $xml);
    }

    /**
     * The button carries the search box's contents, so the file has to hold what
     * was on screen — and nothing else.
     */
    public function test_it_exports_only_what_the_search_matched(): void
    {
        $volvo = $this->truck('Volvo FH 460', 'CVB 407');
        $man = $this->truck('MAN TGX', 'SRD 118');

        $this->route(['origin' => 'Hâncești', 'destination' => 'Brăila', 'vehicle_id' => $volvo->id]);
        $this->route(['origin' => 'Bălți', 'destination' => 'Iași', 'vehicle_id' => $man->id]);

        $xml = $this->sheetXml($this->get(route('dashboard.routes.excel', ['q' => 'MAN']))->getContent());

        $this->assertStringContainsString('Bălți → Iași', $xml);
        $this->assertStringNotContainsString('Hâncești → Brăila', $xml);
        $this->assertStringContainsString('filtrat după', $xml);
    }

    /**
     * Every page of the list, not just the one being looked at — the paginator
     * stops at 15 and an export that did the same would quietly lose the rest.
     */
    public function test_it_exports_beyond_the_first_page(): void
    {
        foreach (range(1, 26) as $i) {
            $this->route(['origin' => 'Oraș '.$i]);
        }

        $xml = $this->sheetXml($this->get(route('dashboard.routes.excel'))->getContent());

        $this->assertStringContainsString('Oraș 1 →', $xml);
        $this->assertStringContainsString('Oraș 26 →', $xml);
    }

    public function test_an_empty_list_still_produces_a_valid_file(): void
    {
        $xml = $this->sheetXml($this->get(route('dashboard.routes.excel'))->getContent());

        $this->assertStringContainsString('RUTE SALVATE', $xml);
        $this->assertStringContainsString('0 rute', $xml);
    }

    public function test_the_list_offers_the_download_only_when_there_is_something_to_download(): void
    {
        $this->get(route('dashboard.routes'))->assertOk()->assertDontSee('Excel');

        $this->route();

        $this->get(route('dashboard.routes'))->assertOk()->assertSee('Excel');
    }
}
