<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RouteCalculation;
use App\Models\Setting;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The saved routes as a spreadsheet.
 *
 * Where the report's export is a summary of a period, this is the ledger: every
 * saved route with every figure it was calculated on, so the file can be sorted,
 * summed or pasted into someone else's accounting sheet.
 *
 * It reads the same `q` the list screen writes and filters through the same
 * scope, so what downloads is exactly what was on screen — all of it, not just
 * the page being looked at.
 */
class RouteExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = (string) $request->query('q', '');

        $routes = RouteCalculation::query()
            ->with('vehicle')
            ->matching($search)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response($this->workbook($routes, $search)->contents(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename().'"',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RouteCalculation>  $routes
     */
    private function workbook($routes, string $search): XlsxWriter
    {
        $lei = Setting::currency();

        $sheet = new XlsxWriter('Rute salvate', [
            11,   // data
            34,   // ruta
            22,   // camion
            11,   // numar
            7,    // zile
            11, 11, 12,           // km dus / intors / total
            10, 12, 10, 12,       // tone si preturi
            8, 12, 13,            // curs, venit EUR, venit MDL
            11, 13,               // litri, combustibil
            12, 12, 10, 11, 11,   // salariu, uzura, vama, rovinieta, alte
            13, 13, 8,            // cost total, profit, marja
            30,                   // note
        ]);

        $bold = static fn (string $text): array => XlsxWriter::cell($text, XlsxWriter::TEXT, true);

        $sheet->row([$bold('RUTE SALVATE')]);
        $sheet->row([sprintf(
            '%d %s%s · sume în %s, unde nu scrie altfel',
            $routes->count(),
            $routes->count() === 1 ? 'rută' : 'rute',
            $search === '' ? '' : ' · filtrat după „'.$search.'”',
            $lei,
        )]);
        $sheet->blank();

        $sheet->row([
            $bold('Data'), $bold('Rută'), $bold('Camion'), $bold('Număr'), $bold('Zile'),
            $bold('Dus (km)'), $bold('Întors (km)'), $bold('Total (km)'),
            $bold('Tone dus'), $bold('Preț/tonă dus (€)'), $bold('Tone retur'), $bold('Preț/tonă retur (€)'),
            $bold('Curs'), $bold('Venit (€)'), $bold('Venit ('.$lei.')'),
            $bold('Motorină (L)'), $bold('Combustibil'),
            $bold('Salariu'), $bold('Uzură'), $bold('Vamă'), $bold('Rovinietă'), $bold('Alte'),
            $bold('Cost total'), $bold('Profit'), $bold('Marjă'), $bold('Note'),
        ]);

        $totals = ['distance' => 0.0, 'litres' => 0.0, 'revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0];

        foreach ($routes as $route) {
            $c = $route->costing();

            $totals['distance'] += $c->totalDistanceKm();
            $totals['litres'] += $c->fuelLitres();
            $totals['revenue'] += $c->revenue();
            $totals['cost'] += $c->totalCost();
            $totals['profit'] += $c->profit();

            $sheet->row([
                XlsxWriter::cell($route->created_at, XlsxWriter::DATE),
                $route->label(),
                $route->vehicle?->name,
                $route->vehicle?->plate,
                XlsxWriter::cell($c->days, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->distanceKm, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->returnDistanceKm, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->totalDistanceKm(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->tonnes, XlsxWriter::DECIMAL),
                XlsxWriter::cell($c->pricePerTonne, XlsxWriter::DECIMAL),
                XlsxWriter::cell($c->returnTonnes, XlsxWriter::DECIMAL),
                XlsxWriter::cell($c->returnPricePerTonne, XlsxWriter::DECIMAL),
                XlsxWriter::cell($c->eurRate, XlsxWriter::DECIMAL),
                XlsxWriter::cell($c->revenueEur(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->revenue(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->fuelLitres(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->fuelCost(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->driverSalary(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->wearCost(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->customs, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->vignette, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->otherCosts, XlsxWriter::NUMBER),
                XlsxWriter::cell($c->totalCost(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->profit(), XlsxWriter::NUMBER),
                XlsxWriter::cell($c->margin(), XlsxWriter::PERCENT),
                $route->notes,
            ]);
        }

        if ($routes->isNotEmpty()) {
            // Weighted by revenue, like everywhere else: an average of the per-route
            // margins would let a 200-lei run count as much as a 20 000-lei one.
            $margin = $totals['revenue'] > 0.0 ? $totals['profit'] / $totals['revenue'] * 100 : 0.0;

            $sheet->row([
                $bold('Total'), null, null, null, null,
                null, null, XlsxWriter::cell($totals['distance'], XlsxWriter::NUMBER, true),
                null, null, null, null,
                null, null, XlsxWriter::cell($totals['revenue'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['litres'], XlsxWriter::NUMBER, true),
                null, null, null, null, null, null,
                XlsxWriter::cell($totals['cost'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['profit'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($margin, XlsxWriter::PERCENT, true),
            ]);
        }

        $sheet->blank();
        $sheet->row(['Fiecare rută poartă cifrele cu care a fost calculată — curs, consum, preț combustibil, uzură, tarifele de salariu. Modificarea valorilor implicite sau a unui vehicul nu le schimbă.']);
        $sheet->row(['Uzura este tariful fix pe kilometru cu care cursele contribuie la cheltuielile camioanelor, nu facturile lor. Acelea se scad o singură dată, în Raport.']);

        return $sheet;
    }

    private function filename(): string
    {
        return 'rute-salvate_'.CarbonImmutable::now()->toDateString().'.xlsx';
    }
}
