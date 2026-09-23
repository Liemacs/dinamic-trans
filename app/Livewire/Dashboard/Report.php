<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Vehicle;
use App\Support\PeriodReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The report screen: pick a stretch of time and a truck, read the sheet that
 * comes out of it, print it.
 *
 * The dates and the truck live in the URL, so a report is a link — the button on
 * the summary opens this screen already set to the twelve months that card was
 * showing, and the printable page is the same link with a bare sheet of paper
 * around it. What a period contains is decided once, in App\Support\PeriodReport.
 */
class Report extends Component
{
    /** Both ends inclusive, as Y-m-d — the language a date input speaks. */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** null is the whole fleet. */
    #[Url(as: 'v')]
    public ?int $vehicle_id = null;

    public function mount(): void
    {
        if ($this->from === '' || $this->to === '') {
            // The first preset is the one a report opens on — see
            // PeriodReport::PRESETS.
            $this->preset(array_key_first(PeriodReport::PRESETS));
        }
    }

    public function preset(string $preset): void
    {
        [$from, $to] = PeriodReport::range($preset, CarbonImmutable::now());

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function period(): array
    {
        return PeriodReport::period($this->from, $this->to, CarbonImmutable::now());
    }

    /** Which preset the dates happen to match, or null for a hand-picked range. */
    #[Computed]
    public function activePreset(): ?string
    {
        [$from, $to] = $this->period;
        $now = CarbonImmutable::now();

        foreach (array_keys(PeriodReport::PRESETS) as $preset) {
            [$presetFrom, $presetTo] = PeriodReport::range($preset, $now);

            if ($presetFrom->isSameDay($from) && $presetTo->isSameDay($to)) {
                return $preset;
            }
        }

        return null;
    }

    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()->orderBy('name')->get();
    }

    #[Computed]
    public function vehicle(): ?Vehicle
    {
        return $this->vehicle_id === null
            ? null
            : $this->vehicles->firstWhere('id', $this->vehicle_id);
    }

    #[Computed]
    public function report(): PeriodReport
    {
        [$from, $to] = $this->period;

        return PeriodReport::for($from, $to, $this->vehicle);
    }

    /** The same report on a bare page, ready for the printer. */
    #[Computed]
    public function printUrl(): string
    {
        return route('dashboard.report.print', $this->query());
    }

    /** And as a spreadsheet, for whatever has to happen to it next. */
    #[Computed]
    public function excelUrl(): string
    {
        return route('dashboard.report.excel', $this->query());
    }

    /**
     * What the printable page and the export need to rebuild this exact report.
     *
     * @return array<string, string|int>
     */
    private function query(): array
    {
        [$from, $to] = $this->period;

        return array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'v' => $this->vehicle_id,
        ], static fn ($value): bool => $value !== null);
    }

    public function render(): View
    {
        return view('livewire.dashboard.report');
    }
}
