<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\RouteCalculation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every saved route, newest first, with the figures each one worked out to.
 */
class RouteList extends Component
{
    use WithPagination;

    /** Rows per page. Low enough that the pager is reachable without scrolling far. */
    private const PER_PAGE = 15;

    /** In the URL so a filtered list can be linked to and survives a reload. */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        // A new search on page 4 would otherwise land on a page the narrowed
        // result set does not have.
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        RouteCalculation::query()->whereKey($id)->delete();

        session()->flash('status', 'Ruta a fost ștearsă.');
    }

    public function render(): View
    {
        return view('livewire.dashboard.route-list', [
            'routes' => $this->routes(),
        ]);
    }

    /**
     * The page of rows the screen shows. What counts as a match lives on the
     * model (RouteCalculation::scopeMatching), so the export downloads exactly
     * the rows being looked at.
     */
    private function routes(): LengthAwarePaginator
    {
        return RouteCalculation::query()
            ->with('vehicle')
            ->matching($this->search)
            /*
             * The id breaks ties on the timestamp. Several routes saved in the
             * same second sort arbitrarily under `latest()` alone, and an
             * unstable sort under a paginator is worse than untidy: the same row
             * can appear on two pages while another is never shown at all.
             */
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }
}
