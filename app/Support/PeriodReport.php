<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RouteCalculation;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The darea de seamă: every route in a stretch of time, line by line, with what
 * the stretch adds up to.
 *
 * Modelled on the paper sheet the fleet keeps per truck per month, minus the
 * columns this app has no data for — the driver, the money handed to him, the
 * sums settled in lei românești and the cash left over from last month. Those
 * are a cash book, not a costing, and inventing them here would be inventing
 * figures. What is left is what the app actually knows: the run, the load, the
 * distance, the diesel, and the money on both sides of it.
 *
 * The routes are taken already filtered: which truck and which dates are the
 * caller's business (App\Livewire\Dashboard\Report), and this class only reads
 * what it is handed. The per-route arithmetic stays in RouteCosting, as
 * everywhere else.
 */
final class PeriodReport
{
    /**
     * The stretches of time the screen offers, in the order it offers them, and
     * the first of them is what a report opens on.
     *
     * The month leads because that is the cadence the fleet reports in — the
     * paper darea de seamă is a sheet per truck per month. The year is a step
     * back from there, not the starting point.
     */
    public const PRESETS = [
        'month' => 'Luna curentă',
        'prev_month' => 'Luna trecută',
        'year' => 'Anul curent',
        'prev_year' => 'Anul trecut',
        'last12' => 'Ultimele 12 luni',
    ];

    /** What a report covers before anyone chooses. */
    private const DEFAULT_PRESET = 'month';

    /**
     * @param  Collection<int, RouteCalculation>  $routes  the routes inside the period, with their vehicles
     * @param  Collection<int, Vehicle>  $vehicles  the trucks whose annual bills this period is charged for
     */
    public function __construct(
        private Collection $routes,
        private Collection $vehicles,
        private CarbonImmutable $from,
        private CarbonImmutable $to,
    ) {}

    /**
     * Everything a report covers, gathered in one place so the screen and the
     * printable page can never disagree about what is in it.
     *
     * A truck named explicitly is charged for its own bills whether it is still
     * in service or not — the report was asked for by name. A fleet-wide report
     * charges the trucks still on the road: one taken out of service has stopped
     * billing, and carrying it would sink every report from here on.
     */
    public static function for(CarbonImmutable $from, CarbonImmutable $to, ?Vehicle $vehicle = null): self
    {
        return new self(
            RouteCalculation::query()
                ->with('vehicle')
                ->whereBetween('created_at', [$from, $to])
                ->when($vehicle !== null, fn ($query) => $query->where('vehicle_id', $vehicle->id))
                ->orderBy('created_at')
                ->get(),
            $vehicle !== null
                ? new Collection([$vehicle])
                : Vehicle::query()->where('active', true)->orderBy('name')->get(),
            $from,
            $to,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $preset, CarbonImmutable $now): array
    {
        return match ($preset) {
            'prev_month' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'prev_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            // Rolling rather than calendar, so it lands on the same twelve months
            // as the "Rezultat anual" card the button comes from.
            'last12' => [$now->startOfMonth()->subMonths(11), $now->endOfMonth()],
            // The current month, and the fallback for a preset name that is not
            // one of the above.
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * Two dates out of whatever the URL carried: whole days, in order, and never
     * missing — anything unusable falls back to the current month. Typing into
     * the "from" field walks past the "to" field on the way to a sensible date,
     * so a reversed pair is swapped rather than refused.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function period(?string $from, ?string $to, CarbonImmutable $now): array
    {
        [$defaultFrom, $defaultTo] = self::range(self::DEFAULT_PRESET, $now);

        $start = self::parse($from) ?? $defaultFrom;
        $end = self::parse($to) ?? $defaultTo;

        return $start->greaterThan($end)
            ? [$end->startOfDay(), $start->endOfDay()]
            : [$start->startOfDay(), $end->endOfDay()];
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            // Whatever was in the URL was not a date; the caller falls back.
            return null;
        }
    }

    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    public function isEmpty(): bool
    {
        return $this->routes->isEmpty();
    }

    /**
     * The period in days, both ends included — a report for the 1st alone is one
     * day, not none.
     */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * One line per route, oldest first, in the order the paper sheet has them.
     *
     * @return list<array{date: CarbonImmutable, label: string, vehicle: string, tonnes: float, return_tonnes: float, distance: float, litres: float, revenue: float, cost: float, profit: float, margin: float}>
     */
    public function rows(): array
    {
        return $this->routes
            ->sortBy(fn (RouteCalculation $route) => $route->created_at)
            ->values()
            ->map(function (RouteCalculation $route): array {
                $costing = $route->costing();

                return [
                    'date' => CarbonImmutable::instance($route->created_at),
                    'label' => $route->label(),
                    'vehicle' => $route->vehicle?->label() ?? 'Fără vehicul',
                    'tonnes' => $costing->tonnes,
                    'return_tonnes' => $costing->returnTonnes,
                    'distance' => $costing->totalDistanceKm(),
                    'litres' => $costing->fuelLitres(),
                    'revenue' => $costing->revenue(),
                    'cost' => $costing->totalCost(),
                    'profit' => $costing->profit(),
                    'margin' => $costing->margin(),
                ];
            })
            ->all();
    }

    /**
     * The line under the table.
     *
     * @return array{routes: int, tonnes: float, return_tonnes: float, distance: float, litres: float, revenue: float, cost: float, profit: float, margin: float}
     */
    public function totals(): array
    {
        $rows = $this->rows();

        $sum = static fn (string $key): float => (float) array_sum(array_column($rows, $key));

        $revenue = $sum('revenue');
        $profit = $sum('profit');

        return [
            'routes' => count($rows),
            'tonnes' => $sum('tonnes'),
            'return_tonnes' => $sum('return_tonnes'),
            'distance' => $sum('distance'),
            'litres' => $sum('litres'),
            'revenue' => $revenue,
            'cost' => $sum('cost'),
            'profit' => $profit,
            // Weighted by revenue, like everywhere else: an average of per-route
            // margins would let a 200 lei run outvote a 20 000 lei one.
            'margin' => $revenue > 0.0 ? $profit / $revenue * 100 : 0.0,
        ];
    }

    /**
     * What the period leaves behind once the trucks are paid for.
     *
     * The same chain as the summary screen, and for the same reason: a route's
     * profit already carries `km × uzură/km`, which is how a truck's annual
     * bills are spread over its driving. So the road costs here start without
     * wear, and the bills are charged once — pro-rated to the length of the
     * period, because a report on three months should not carry a year of
     * insurance.
     *
     * @return array{revenue: float, road_cost: float, contribution: float, wear: float, fixed_annual: float, fixed: float, share: float, result: float, coverage: float, vehicles: int}
     */
    public function bottomLine(): array
    {
        $revenue = 0.0;
        $roadCost = 0.0;
        $wear = 0.0;

        foreach ($this->routes as $route) {
            $costing = $route->costing();

            $revenue += $costing->revenue();
            $roadCost += $costing->totalCost() - $costing->wearCost();
            $wear += $costing->wearCost();
        }

        $fixedAnnual = (float) $this->vehicles->sum(static fn (Vehicle $vehicle): float => $vehicle->annualTotal());

        // Charged by the day rather than by the month, so a report on a single
        // week is not billed a whole month of insurance.
        $share = $this->days() / ($this->from->isLeapYear() ? 366 : 365);
        $fixed = $fixedAnnual * $share;

        $contribution = $revenue - $roadCost;

        return [
            'revenue' => $revenue,
            'road_cost' => $roadCost,
            'contribution' => $contribution,
            'wear' => $wear,
            'fixed_annual' => $fixedAnnual,
            'fixed' => $fixed,
            'share' => $share,
            'result' => $contribution - $fixed,
            'coverage' => $fixed > 0.0 ? $wear / $fixed * 100 : 0.0,
            'vehicles' => $this->vehicles->count(),
        ];
    }
}
