<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Support\PeriodReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The report on a bare sheet of paper.
 *
 * A page of its own rather than a print stylesheet over the dashboard: the shell
 * is a fixed sidebar around one scrolling pane, and talking a browser out of
 * that for the printer costs more rules than rendering the sheet on its own.
 *
 * It reads the same query string the report screen writes, and builds the report
 * through the same factory — so what is printed is what was on screen.
 */
class ReportPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$from, $to] = PeriodReport::period(
            $request->query('from'),
            $request->query('to'),
            CarbonImmutable::now(),
        );

        $vehicle = $request->query('v') === null
            ? null
            : Vehicle::query()->find($request->query('v'));

        return view('dashboard.report-print', [
            'report' => PeriodReport::for($from, $to, $vehicle),
            'vehicle' => $vehicle,
            'back' => route('dashboard.report', array_filter([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'v' => $vehicle?->id,
            ])),
        ]);
    }
}
