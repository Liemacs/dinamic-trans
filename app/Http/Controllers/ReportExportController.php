<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Vehicle;
use App\Support\PeriodReport;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The same report as a spreadsheet.
 *
 * It reads the query string the report screen writes and builds the report
 * through the same factory as the screen and the printable page, so the three
 * can only ever differ in how they are laid out — never in what they say.
 *
 * The figures go in as numbers rather than as formatted text: the point of
 * handing someone a spreadsheet is that they can sum a column, sort by margin or
 * drop the lines into their own accounting sheet.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        [$from, $to] = PeriodReport::period(
            $request->query('from'),
            $request->query('to'),
            CarbonImmutable::now(),
        );

        $vehicle = $request->query('v') === null
            ? null
            : Vehicle::query()->find($request->query('v'));

        $report = PeriodReport::for($from, $to, $vehicle);

        return response($this->workbook($report, $vehicle)->contents(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($report, $vehicle).'"',
        ]);
    }

    private function workbook(PeriodReport $report, ?Vehicle $vehicle): XlsxWriter
    {
        $lei = Setting::currency();
        $totals = $report->totals();
        $bottom = $report->bottomLine();

        // With one truck named at the top, a column repeating it on every line
        // would be a column of the same word — same as on the printed sheet.
        $withVehicle = $vehicle === null;

        $sheet = new XlsxWriter('Darea de seamă', $withVehicle
            ? [13, 34, 22, 10, 11, 11, 12, 13, 13, 13, 8]
            : [13, 34, 10, 11, 11, 12, 13, 13, 13, 8]);

        $bold = static fn (string $text): array => XlsxWriter::cell($text, XlsxWriter::TEXT, true);

        /*
         * The truck column is there or it is not, and it sits in the middle of
         * every row — so each row is built as head, optional truck, tail rather
         * than as one list with holes filtered out of it.
         *
         * @param  list<mixed>  $head
         * @param  list<mixed>  $tail
         */
        $line = static function (array $head, mixed $truck, array $tail) use ($withVehicle): array {
            return $withVehicle
                ? [...$head, $truck, ...$tail]
                : [...$head, ...$tail];
        };

        $sheet->row([$bold('DAREA DE SEAMĂ')]);
        $sheet->row([sprintf(
            'Perioada %s – %s · Camion: %s · %d %s · sume în %s',
            $report->from()->format('d.m.Y'),
            $report->to()->format('d.m.Y'),
            $vehicle?->label() ?? 'toată flota',
            $totals['routes'],
            $totals['routes'] === 1 ? 'rută' : 'rute',
            $lei,
        )]);
        $sheet->blank();

        $sheet->row($line(
            [$bold('Data'), $bold('Sarcina')],
            $bold('Camion'),
            [
                $bold('Tone dus'),
                $bold('Tone retur'),
                $bold('Distanță (km)'),
                $bold('Motorină (L)'),
                $bold('Venit ('.$lei.')'),
                $bold('Cheltuit ('.$lei.')'),
                $bold('Profit ('.$lei.')'),
                $bold('Marjă'),
            ],
        ));

        foreach ($report->rows() as $row) {
            $sheet->row($line(
                [XlsxWriter::cell($row['date'], XlsxWriter::DATE), $row['label']],
                $row['vehicle'],
                [
                    XlsxWriter::cell($row['tonnes'], XlsxWriter::DECIMAL),
                    XlsxWriter::cell($row['return_tonnes'], XlsxWriter::DECIMAL),
                    XlsxWriter::cell($row['distance'], XlsxWriter::NUMBER),
                    XlsxWriter::cell($row['litres'], XlsxWriter::NUMBER),
                    XlsxWriter::cell($row['revenue'], XlsxWriter::NUMBER),
                    XlsxWriter::cell($row['cost'], XlsxWriter::NUMBER),
                    XlsxWriter::cell($row['profit'], XlsxWriter::NUMBER),
                    XlsxWriter::cell($row['margin'], XlsxWriter::PERCENT),
                ],
            ));
        }

        $sheet->row($line(
            [$bold('Total'), null],
            null,
            [
                XlsxWriter::cell($totals['tonnes'], XlsxWriter::DECIMAL, true),
                XlsxWriter::cell($totals['return_tonnes'], XlsxWriter::DECIMAL, true),
                XlsxWriter::cell($totals['distance'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['litres'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['revenue'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['cost'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['profit'], XlsxWriter::NUMBER, true),
                XlsxWriter::cell($totals['margin'], XlsxWriter::PERCENT, true),
            ],
        ));

        $sheet->blank();
        $sheet->row([$bold('REZULTATUL PERIOADEI')]);
        $sheet->row(['Venit din rute', XlsxWriter::cell($bottom['revenue'], XlsxWriter::NUMBER)]);
        $sheet->row(['Costuri de drum (combustibil, salariu, vamă, rovinietă, alte)', XlsxWriter::cell(-$bottom['road_cost'], XlsxWriter::NUMBER)]);
        $sheet->row([$bold('Contribuție'), XlsxWriter::cell($bottom['contribution'], XlsxWriter::NUMBER, true)]);
        $sheet->row([
            sprintf('Cheltuieli camioane (%d %s din %s/an)', $report->days(), $report->days() === 1 ? 'zi' : 'zile', number_format($bottom['fixed_annual'], 0, ',', '.')),
            XlsxWriter::cell(-$bottom['fixed'], XlsxWriter::NUMBER),
        ]);
        $sheet->row([$bold('Rezultat'), XlsxWriter::cell($bottom['result'], XlsxWriter::NUMBER, true)]);

        $sheet->blank();
        $sheet->row(['Uzura nu se scade de două ori: profitul fiecărei rute conține deja uzura pe kilometru — un tarif fix, cu care cursele contribuie la cheltuielile camioanelor. „Costuri de drum" pornește fără ea, iar cheltuielile reale ale camioanelor intră o singură dată.']);
        $sheet->row([sprintf(
            'Prin kilometrii din perioadă s-au recuperat %s %s din cheltuielile camioanelor.',
            number_format($bottom['wear'], 0, ',', '.'),
            $lei,
        )]);
        $sheet->row(['O rută intră în perioadă după data la care a fost salvată, cu cifrele înghețate atunci.']);

        return $sheet;
    }

    private function filename(PeriodReport $report, ?Vehicle $vehicle): string
    {
        return implode('_', array_filter([
            'darea-de-seama',
            $vehicle === null ? null : Str::slug($vehicle->name),
            $report->from()->toDateString(),
            $report->to()->toDateString(),
        ])).'.xlsx';
    }
}
