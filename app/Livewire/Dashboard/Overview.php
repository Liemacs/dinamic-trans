<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\RouteCalculation;
use App\Models\Vehicle;
use App\Support\FleetSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the saved routes add up to: the totals, the shape of them over time, the
 * share each truck carries, and what a year leaves behind once the trucks have
 * been paid for.
 *
 * All of it is read from the same collection of routes, loaded once — see
 * self::routes(). The grouping lives in App\Support\FleetSummary.
 */
class Overview extends Component
{
    /**
     * Which window the two charts cover. In the URL so a view of the year can be
     * linked to, and validated on the way out of it — see FleetSummary::period().
     */
    #[Url(as: 'p', except: '12')]
    public string $period = '12';

    /**
     * Every saved route, loaded once and reused by every computed property
     * below. The figures are derived per row (RouteCalculation::costing), so
     * they cannot be summed in SQL without duplicating the arithmetic there —
     * and the table this reports on is a working set, not a warehouse.
     */
    #[Computed]
    public function routes(): Collection
    {
        return RouteCalculation::query()->with('vehicle')->latest()->get();
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()->orderBy('name')->get();
    }

    #[Computed]
    public function summary(): FleetSummary
    {
        return new FleetSummary($this->routes, $this->vehicles, CarbonImmutable::now());
    }

    /** The period key with anything unexpected in the URL folded back to the default. */
    #[Computed]
    public function window(): string
    {
        return FleetSummary::period($this->period);
    }

    /**
     * @return array{count: int, revenue: float, profit: float, margin: float, profit_per_km: float, distance: float}
     */
    #[Computed]
    public function totals(): array
    {
        $routes = $this->routes;
        $costings = $routes->map(fn (RouteCalculation $route) => $route->costing());

        $revenue = (float) $costings->sum(fn ($costing): float => $costing->revenue());
        $profit = (float) $costings->sum(fn ($costing): float => $costing->profit());
        $distance = (float) $costings->sum(fn ($costing): float => $costing->totalDistanceKm());

        return [
            'count' => $routes->count(),
            'revenue' => $revenue,
            'profit' => $profit,
            // Weighted by revenue rather than an average of per-route margins:
            // a 200 € run at 40% and a 20 000 € run at 2% are not one 21% fleet.
            'margin' => $revenue > 0.0 ? $profit / $revenue * 100 : 0.0,
            'profit_per_km' => $distance > 0.0 ? $profit / $distance : 0.0,
            'distance' => $distance,
        ];
    }

    #[Computed]
    public function series(): array
    {
        return $this->summary->series($this->window);
    }

    #[Computed]
    public function perVehicle(): array
    {
        return $this->summary->perVehicle($this->window);
    }

    #[Computed]
    public function annual(): array
    {
        return $this->summary->annual();
    }

    #[Computed]
    public function recent(): Collection
    {
        return $this->routes->take(6);
    }

    public function setPeriod(string $period): void
    {
        $this->period = FleetSummary::period($period);
    }

    public function render(): View
    {
        return view('livewire.dashboard.overview');
    }
}
