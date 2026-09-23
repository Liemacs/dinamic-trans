<?php

declare(strict_types=1);

use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RouteExportController;
use App\Http\Controllers\ReportPrintController;
use App\Models\RouteCalculation;
use App\Support\DashboardNav;
use Illuminate\Support\Facades\Route;

/*
 * One route per leaf in App\Support\DashboardNav, registered from the same map
 * the sidebar renders from — so a section can never appear in the nav without a
 * URL behind it, or the other way round.
 *
 * Each leaf names the view that draws it. The heading and the description come
 * from lang/ro/dashboard.php via the nav key, so the page and the sidebar always
 * agree on what a section is called.
 */
$views = [
    'overview' => 'dashboard.overview',
    'calculator' => 'dashboard.calculator',
    'routes' => 'dashboard.routes',
    'report' => 'dashboard.report',
    'vehicles' => 'dashboard.vehicles',
    'defaults' => 'dashboard.defaults',
];

$register = static function (array $leaf) use ($views): void {
    $key = $leaf['key'];

    Route::get($leaf['path'], fn () => view($views[$key], ['section' => $key]))->name($leaf['route']);
};

foreach (DashboardNav::items() as $item) {
    if (($item['type'] ?? 'link') === 'group') {
        foreach ($item['children'] ?? [] as $child) {
            $register($child);
        }

        continue;
    }

    $register($item);
}

/*
 * Editing a route that is already saved. Not in the sidebar either — you arrive
 * at it from the row you want to change. The name sits under `dashboard.routes.`
 * so DashboardNav::isCurrent keeps "Rute salvate" lit while the form is open.
 */
Route::get('rute/{route}/modifica', fn (RouteCalculation $route) => view('dashboard.route-edit', ['route' => $route]))
    ->name('dashboard.routes.edit');

/*
 * The two ways a report leaves the screen, neither of which belongs in the
 * sidebar — you arrive at them from the report you are already looking at. Both
 * read the same query string the report screen writes.
 */
Route::get('raport/tipar', ReportPrintController::class)->name('dashboard.report.print');
Route::get('raport/excel', ReportExportController::class)->name('dashboard.report.excel');

/*
 * The saved routes as a spreadsheet. Outside the nav map like the report's own
 * downloads: you arrive at it from the list you are already looking at, carrying
 * that list's search with you.
 */
Route::get('rute/excel', RouteExportController::class)->name('dashboard.routes.excel');
