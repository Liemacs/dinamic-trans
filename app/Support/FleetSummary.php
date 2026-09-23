<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RouteCalculation;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The saved routes read three ways: over time, per truck, and against the bills
 * the trucks bring in on their own.
 *
 * The arithmetic per route stays where it has always been
 * (App\Support\RouteCosting) — this class only groups and adds up, so a change
 * to how a route is costed moves every figure on the summary with it.
 *
 * Two things are worth knowing before reading the numbers below.
 *
 * **The date axis is `created_at`.** A route carries no date of its own; the
 * only date on the row is the moment it was saved. So "venit lunar" means the
 * routes calculated that month, and the screen says so rather than implying a
 * dispatch log.
 *
 * **Wear is not subtracted twice.** A route's profit already carries
 * `uzură = km × uzură/km`, which is exactly how a truck's annual bills are
 * spread over its year of driving. The annual result below therefore starts
 * from the contribution *before* wear and subtracts the real annual bills once
 * — anything else would charge the fleet for its insurance twice.
 */
final class FleetSummary
{
    /**
     * The windows the summary offers, in the order the switch renders them.
     * Keys are what lands in the URL, so they are short and stable.
     */
    public const PERIODS = [
        '6' => '6 luni',
        '12' => '12 luni',
        '24' => '24 luni',
        'all' => 'Tot',
    ];

    /** How long the annual result looks back — rolling, so it survives January. */
    private const ANNUAL_MONTHS = 12;

    /**
     * Beyond this many months a column per month is a picket fence, so "Tot"
     * steps up to quarters and then to years.
     */
    private const MAX_MONTH_COLUMNS = 24;

    private const MAX_QUARTER_COLUMNS = 24;

    /** @var list<string> */
    private const MONTHS_SHORT = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sept', 'oct', 'nov', 'dec'];

    /** @var list<string> */
    private const MONTHS_LONG = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

    /**
     * @param  Collection<int, RouteCalculation>  $routes  every saved route, with its vehicle loaded
     * @param  Collection<int, Vehicle>  $vehicles  the whole fleet, active or not
     * @param  CarbonImmutable  $now  passed in rather than read, so a test can stand anywhere in time
     */
    public function __construct(
        private Collection $routes,
        private Collection $vehicles,
        private CarbonImmutable $now,
    ) {}

    /**
     * A period key that is safe to use. The switch writes this into the URL, so
     * whatever comes back from it is user input.
     */
    public static function period(?string $key): string
    {
        return $key !== null && array_key_exists($key, self::PERIODS) ? $key : '12';
    }

    // -- Over time ------------------------------------------------------------

    /**
     * Revenue, cost and profit per bucket, oldest first, with empty buckets kept
     * — a month with no routes is a fact about the month, and dropping it would
     * close the gap and make an idle spring look busy.
     *
     * @return array{
     *     granularity: string,
     *     buckets: list<array{key: string, label: string, year: string|null, title: string, revenue: float, cost: float, profit: float, count: int}>,
     *     max: float, revenue: float, cost: float, profit: float, count: int
     * }
     */
    public function series(string $period): array
    {
        [$granularity, $start] = $this->window($period);

        $buckets = [];
        $cursor = $start;
        $last = $this->floor($this->now, $granularity);
        $first = true;

        while ($cursor->lessThanOrEqualTo($last)) {
            $buckets[$this->bucketKey($cursor, $granularity)] = [
                'key' => $this->bucketKey($cursor, $granularity),
                'label' => $this->bucketLabel($cursor, $granularity),
                // The year is written under the label only where it changes, so
                // a twelve-column strip is not twelve repetitions of "'26".
                'year' => $this->bucketYear($cursor, $granularity, $first),
                'title' => $this->bucketTitle($cursor, $granularity),
                'revenue' => 0.0,
                'cost' => 0.0,
                'profit' => 0.0,
                'count' => 0,
            ];

            $cursor = $this->advance($cursor, $granularity);
            $first = false;
        }

        foreach ($this->routesSince($start) as $route) {
            $key = $this->bucketKey($route->created_at, $granularity);

            if (! isset($buckets[$key])) {
                continue;
            }

            $costing = $route->costing();

            $buckets[$key]['revenue'] += $costing->revenue();
            $buckets[$key]['cost'] += $costing->totalCost();
            $buckets[$key]['profit'] += $costing->profit();
            $buckets[$key]['count']++;
        }

        $buckets = array_values($buckets);

        return [
            'granularity' => $granularity,
            'buckets' => $buckets,
            /*
             * The tallest column is the larger of revenue and cost: a month that
             * lost money is drawn to its cost, with the uncovered part on top,
             * and a scale capped at revenue would clip it.
             */
            'max' => (float) max([0.0, ...array_map(
                static fn (array $bucket): float => max($bucket['revenue'], $bucket['cost']),
                $buckets,
            )]),
            'revenue' => (float) array_sum(array_column($buckets, 'revenue')),
            'cost' => (float) array_sum(array_column($buckets, 'cost')),
            'profit' => (float) array_sum(array_column($buckets, 'profit')),
            'count' => (int) array_sum(array_column($buckets, 'count')),
        ];
    }

    // -- Per truck ------------------------------------------------------------

    /**
     * What each truck brought in over the window.
     *
     * Active trucks with no routes in the window are listed too, at zero: an
     * idle truck still bills its insurance, and leaving it out of the chart
     * would hide exactly the fact worth seeing. Routes saved without a vehicle
     * are collected into one row rather than dropped, so the rows still add up
     * to the total above them.
     *
     * @return list<array{key: string, label: string, note: string|null, routes: int, distance: float, revenue: float, profit: float, margin: float, fixed: float}>
     */
    public function perVehicle(string $period): array
    {
        [, $start] = $this->window($period);

        $rows = [];

        foreach ($this->vehicles as $vehicle) {
            if (! $vehicle->active) {
                continue;
            }

            $rows['v'.$vehicle->id] = $this->emptyVehicleRow($vehicle);
        }

        foreach ($this->routesSince($start) as $route) {
            $vehicle = $route->vehicle;
            $key = $vehicle === null ? 'none' : 'v'.$vehicle->id;

            $rows[$key] ??= $vehicle === null
                ? [
                    'key' => 'none',
                    'label' => 'Fără vehicul',
                    'note' => 'rute salvate fără camion',
                    'routes' => 0, 'distance' => 0.0, 'revenue' => 0.0, 'profit' => 0.0, 'margin' => 0.0, 'fixed' => 0.0,
                ]
                // Reached when the truck is inactive or was deleted after the
                // route was saved: the run happened, so it still counts.
                : $this->emptyVehicleRow($vehicle);

            $costing = $route->costing();

            $rows[$key]['routes']++;
            $rows[$key]['distance'] += $costing->totalDistanceKm();
            $rows[$key]['revenue'] += $costing->revenue();
            $rows[$key]['profit'] += $costing->profit();
        }

        $rows = array_map(static function (array $row): array {
            $row['margin'] = $row['revenue'] > 0.0 ? $row['profit'] / $row['revenue'] * 100 : 0.0;

            return $row;
        }, array_values($rows));

        usort($rows, static fn (array $a, array $b): int => [$b['revenue'], $b['routes'], $a['label']] <=> [$a['revenue'], $a['routes'], $b['label']]);

        return $rows;
    }

    // -- The year -------------------------------------------------------------

    /**
     * What is left over in a year once the trucks have been paid for.
     *
     * The chain, line by line, is what the card draws:
     *
     *     venit rute            what the customers paid
     *   − costuri de drum       fuel, driver, customs, vignette, other
     *   = contribuție           what the runs left behind
     *   − cheltuieli camioane   insurance, service, tyres, GPS, for a year
     *   = rezultat              the answer to "cu cât sunt în plus"
     *
     * Wear is deliberately absent from "costuri de drum": it is the annual
     * bills in per-kilometre clothing, and they are subtracted whole one line
     * later. `wear` below reports how much of those bills the kilometres
     * actually recovered — at the rates each route was frozen with — which is
     * the honest way to ask whether the fleet drives enough to pay for itself.
     *
     * @return array{
     *     revenue: float, road_cost: float, contribution: float, wear: float,
     *     fixed: float, result: float, coverage: float, months: int,
     *     partial: bool, run_rate: float|null, vehicles: int, routes: int
     * }
     */
    public function annual(): array
    {
        $start = $this->now->startOfMonth()->subMonths(self::ANNUAL_MONTHS - 1);
        $routes = $this->routesSince($start);

        $revenue = 0.0;
        $roadCost = 0.0;
        $wear = 0.0;

        foreach ($routes as $route) {
            $costing = $route->costing();

            $revenue += $costing->revenue();
            $roadCost += $costing->totalCost() - $costing->wearCost();
            $wear += $costing->wearCost();
        }

        $contribution = $revenue - $roadCost;
        $active = $this->vehicles->filter(static fn (Vehicle $vehicle): bool => $vehicle->active);
        $fixed = (float) $active->sum(static fn (Vehicle $vehicle): float => $vehicle->annualTotal());

        // How much of the year the saved routes actually cover. A fleet three
        // months into keeping records has a three-month contribution against a
        // full year of bills, and calling that "the year" would read as a loss.
        $months = $this->monthsCovered($start);

        return [
            'revenue' => $revenue,
            'road_cost' => $roadCost,
            'contribution' => $contribution,
            'wear' => $wear,
            'fixed' => $fixed,
            'result' => $contribution - $fixed,
            'coverage' => $fixed > 0.0 ? $wear / $fixed * 100 : 0.0,
            'months' => $months,
            'partial' => $months > 0 && $months < self::ANNUAL_MONTHS,
            'run_rate' => $months > 0 && $months < self::ANNUAL_MONTHS
                ? $contribution / $months * self::ANNUAL_MONTHS - $fixed
                : null,
            'vehicles' => $active->count(),
            'routes' => $routes->count(),
        ];
    }

    // -- Internals ------------------------------------------------------------

    /**
     * The window a period key stands for: how wide the buckets are, and where
     * the first one starts.
     *
     * @return array{0: string, 1: CarbonImmutable}
     */
    private function window(string $period): array
    {
        $thisMonth = $this->now->startOfMonth();

        if ($period !== 'all') {
            return ['month', $thisMonth->subMonths(max(1, (int) $period) - 1)];
        }

        $earliest = $this->earliest() ?? $thisMonth;
        $months = (int) $earliest->startOfMonth()->diffInMonths($thisMonth) + 1;

        if ($months <= self::MAX_MONTH_COLUMNS) {
            return ['month', $earliest->startOfMonth()];
        }

        return $months <= self::MAX_QUARTER_COLUMNS * 3
            ? ['quarter', $earliest->startOfQuarter()]
            : ['year', $earliest->startOfYear()];
    }

    /** The oldest saved route, or null on an empty table. */
    private function earliest(): ?CarbonImmutable
    {
        $earliest = $this->routes
            ->map(static fn (RouteCalculation $route) => $route->created_at)
            ->filter()
            ->min();

        return $earliest === null ? null : CarbonImmutable::instance($earliest);
    }

    /**
     * @return Collection<int, RouteCalculation>
     */
    private function routesSince(CarbonImmutable $start): Collection
    {
        return $this->routes->filter(
            static fn (RouteCalculation $route): bool => $route->created_at !== null
                && $route->created_at->greaterThanOrEqualTo($start),
        );
    }

    /**
     * Whole months of records behind the annual figures, counted from the first
     * route inside the window — capped at the window itself.
     */
    private function monthsCovered(CarbonImmutable $start): int
    {
        $earliest = $this->earliest();

        if ($earliest === null) {
            return 0;
        }

        $from = $earliest->greaterThan($start) ? $earliest : $start;

        return min(
            self::ANNUAL_MONTHS,
            (int) $from->startOfMonth()->diffInMonths($this->now->startOfMonth()) + 1,
        );
    }

    private function floor(CarbonImmutable $at, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'year' => $at->startOfYear(),
            'quarter' => $at->startOfQuarter(),
            default => $at->startOfMonth(),
        };
    }

    private function advance(CarbonImmutable $at, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'year' => $at->addYear(),
            'quarter' => $at->addQuarter(),
            default => $at->addMonth(),
        };
    }

    private function bucketKey(CarbonInterface $at, string $granularity): string
    {
        return match ($granularity) {
            'year' => $at->format('Y'),
            'quarter' => $at->format('Y').'-T'.$at->quarter,
            default => $at->format('Y-m'),
        };
    }

    private function bucketLabel(CarbonImmutable $at, string $granularity): string
    {
        return match ($granularity) {
            'year' => $at->format('Y'),
            'quarter' => 'T'.$at->quarter,
            default => self::MONTHS_SHORT[$at->month - 1],
        };
    }

    /**
     * The year under a column, or null where it would only repeat the one
     * before it.
     */
    private function bucketYear(CarbonImmutable $at, string $granularity, bool $first): ?string
    {
        if ($granularity === 'year') {
            return null;
        }

        $starts = $granularity === 'quarter' ? $at->quarter === 1 : $at->month === 1;

        return $first || $starts ? $at->format('Y') : null;
    }

    private function bucketTitle(CarbonImmutable $at, string $granularity): string
    {
        return match ($granularity) {
            'year' => $at->format('Y'),
            'quarter' => 'trimestrul '.$at->quarter.' '.$at->format('Y'),
            default => self::MONTHS_LONG[$at->month - 1].' '.$at->format('Y'),
        };
    }

    /**
     * @return array{key: string, label: string, note: string|null, routes: int, distance: float, revenue: float, profit: float, margin: float, fixed: float}
     */
    private function emptyVehicleRow(Vehicle $vehicle): array
    {
        $note = $vehicle->plate ?: 'fără număr';

        return [
            'key' => 'v'.$vehicle->id,
            'label' => $vehicle->name,
            'note' => $vehicle->active ? $note : $note.' · inactiv',
            'routes' => 0,
            'distance' => 0.0,
            'revenue' => 0.0,
            'profit' => 0.0,
            'margin' => 0.0,
            'fixed' => $vehicle->annualTotal(),
        ];
    }
}
