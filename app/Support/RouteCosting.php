<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a route earns, what it costs, and what it leaves behind.
 *
 * The one place the arithmetic lives. The live calculator builds one of these
 * from unsaved form input on every keystroke, and a saved RouteCalculation
 * builds one from its own columns — so the figure on the form and the figure in
 * the table can never drift apart.
 *
 * The model follows the source spreadsheet (Calculator_rute.xlsx, sheet "Rute"):
 *
 *     preț rută (EUR) = tone × preț per tonă
 *     preț rută (MDL) = preț rută (EUR) × curs
 *     combustibil (L) = distanță totală × consum   // dus + întors; consum is L/km
 *     cost combustibil = combustibil × preț/L
 *     salariu          = prima zi + (zile − 1) × zi adițională
 *     uzură            = distanță × uzură/km
 *     profit           = preț rută (MDL) − combustibil − salariu − uzură − vamă − rovinietă
 *
 * Two things sit on top of it. `otherCosts` is a catch-all the spreadsheet has no
 * column for. And the distance is split in two: the run out and the way home. The
 * spreadsheet had a single "Distanța" column and left it to the reader whether it
 * meant one leg or both — fuel and wear are charged on the sum here, so the
 * question has an answer.
 *
 * The way home is `returnDistanceKm` whether the truck carries anything or not.
 * When it does — a detour to a third point for someone else's cargo — that leg is
 * simply longer, and it brings its own revenue and a loading day for the driver.
 */
final readonly class RouteCosting
{
    public function __construct(
        public float $distanceKm,
        /** Litres per kilometre. */
        public float $consumption,
        /** Lei per litre. */
        public float $fuelPrice,
        /** Load carried, in tonnes. */
        public float $tonnes = 0.0,
        /** Euro per tonne — what the customer is quoted. */
        public float $pricePerTonne = 0.0,
        /** Lei per euro, at the time the route was priced. */
        public float $eurRate = 0.0,
        /** Insurance, service, tyres and the GPS subscription, per kilometre. */
        public float $wearPerKm = 0.0,
        public float $customs = 0.0,
        public float $vignette = 0.0,
        public float $otherCosts = 0.0,
        /** Days on the road, the first one included. */
        public int $days = 0,
        public float $driverFirstDay = 0.0,
        public float $driverExtraDay = 0.0,
        /*
         * The way home. Present on every run: the truck burns fuel and wears
         * itself out coming back whether it carries anything or not, so this is
         * the leg B → A — or B → C → A when it detours to pick up a return load.
         */
        public float $returnDistanceKm = 0.0,
        /*
         * The return load itself. The truck does not always come home empty:
         * sometimes it stops at a third point, picks up someone else's cargo and
         * carries it back, which pays and costs the driver a loading day.
         *
         * All three are zero on a run that comes home empty, so the arithmetic
         * below needs no branch for it.
         */
        public float $returnTonnes = 0.0,
        public float $returnPricePerTonne = 0.0,
        public float $loadingDayBonus = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Raw form values; anything missing or
     *                                       non-numeric counts as zero.
     */
    public static function fromArray(array $input): self
    {
        $number = static fn (string $key): float => is_numeric($input[$key] ?? null)
            ? (float) $input[$key]
            : 0.0;

        return new self(
            distanceKm: $number('distance_km'),
            consumption: $number('consumption'),
            fuelPrice: $number('fuel_price'),
            tonnes: $number('tonnes'),
            pricePerTonne: $number('price_per_tonne'),
            eurRate: $number('eur_rate'),
            wearPerKm: $number('wear_per_km'),
            customs: $number('customs'),
            vignette: $number('vignette'),
            otherCosts: $number('other_costs'),
            days: (int) $number('days'),
            driverFirstDay: $number('driver_first_day'),
            driverExtraDay: $number('driver_extra_day'),
            returnDistanceKm: $number('return_distance_km'),
            returnTonnes: $number('return_tonnes'),
            returnPricePerTonne: $number('return_price_per_tonne'),
            loadingDayBonus: $number('loading_day_bonus'),
        );
    }

    // -- Revenue --------------------------------------------------------------

    /**
     * Whether this run picks up cargo on the way home.
     *
     * Deliberately not a question about kilometres: every route has a way home,
     * and counting that as a return load would mark them all as carrying one.
     */
    public function hasReturnLoad(): bool
    {
        return $this->returnTonnes > 0.0
            || $this->returnPricePerTonne > 0.0
            || $this->loadingDayBonus > 0.0;
    }

    /** Every kilometre the truck covers, the detour for the return load included. */
    public function totalDistanceKm(): float
    {
        return $this->distanceKm + $this->returnDistanceKm;
    }

    public function totalTonnes(): float
    {
        return $this->tonnes + $this->returnTonnes;
    }

    /** The outbound load, in euro. */
    public function outboundRevenueEur(): float
    {
        return $this->tonnes * $this->pricePerTonne;
    }

    /** The load carried home, in euro. Zero when the truck comes back empty. */
    public function returnRevenueEur(): float
    {
        return $this->returnTonnes * $this->returnPricePerTonne;
    }

    /** Both loads together, in euro. */
    public function revenueEur(): float
    {
        return $this->outboundRevenueEur() + $this->returnRevenueEur();
    }

    /** The same figure in lei, at the rate the route was priced on. */
    public function revenue(): float
    {
        return $this->revenueEur() * $this->eurRate;
    }

    // -- Costs ----------------------------------------------------------------

    public function fuelLitres(): float
    {
        return $this->totalDistanceKm() * $this->consumption;
    }

    public function fuelCost(): float
    {
        return $this->fuelLitres() * $this->fuelPrice;
    }

    public function wearCost(): float
    {
        return $this->totalDistanceKm() * $this->wearPerKm;
    }

    /**
     * The days on the road. The first is paid at its own rate and every day after
     * it at a lower one, so a one-day run is the first day alone. Days below one
     * earn nothing: a route nobody has put a duration on has no driver on it yet.
     */
    public function driverDaysPay(): float
    {
        return $this->days >= 1
            ? $this->driverFirstDay + ($this->days - 1) * $this->driverExtraDay
            : 0.0;
    }

    /**
     * Everything the driver is paid for the run: the days, plus the loading day
     * on a route that picks up a load for the way home.
     *
     * The loading payment is on top of the day rate rather than in place of it —
     * it is paid for the work of loading, and that day is still a day on the
     * road. Raise `days` as well if the detour makes the trip longer.
     */
    public function driverSalary(): float
    {
        return $this->driverDaysPay() + $this->loadingDayBonus;
    }

    public function totalCost(): float
    {
        return $this->fuelCost()
            + $this->driverSalary()
            + $this->wearCost()
            + $this->customs
            + $this->vignette
            + $this->otherCosts;
    }

    // -- Result ---------------------------------------------------------------

    public function profit(): float
    {
        return $this->revenue() - $this->totalCost();
    }

    /**
     * Profit as a percentage of revenue. Zero revenue has no margin to speak of
     * — reporting -100% for a route nobody has priced yet would read as a loss
     * rather than as a blank.
     */
    public function margin(): float
    {
        $revenue = $this->revenue();

        return $revenue > 0.0 ? $this->profit() / $revenue * 100 : 0.0;
    }

    public function revenuePerKm(): float
    {
        return $this->perKm($this->revenue());
    }

    public function costPerKm(): float
    {
        return $this->perKm($this->totalCost());
    }

    public function profitPerKm(): float
    {
        return $this->perKm($this->profit());
    }

    /**
     * The break-even price for the run: what it has to earn to cover its own
     * costs. Same number as totalCost, named for how the calculator uses it.
     */
    public function breakEven(): float
    {
        return $this->totalCost();
    }

    /**
     * The price per tonne that would break even, which is the figure to argue
     * over with a customer. Zero tonnes has none.
     */
    public function breakEvenPerTonneEur(): float
    {
        return $this->totalTonnes() > 0.0 && $this->eurRate > 0.0
            ? $this->totalCost() / $this->eurRate / $this->totalTonnes()
            : 0.0;
    }

    /**
     * Whether there is enough input for the figures to mean anything. A route
     * with no distance produces a tidy row of zeroes that looks like a result.
     */
    public function isComplete(): bool
    {
        return $this->totalDistanceKm() > 0.0;
    }

    /**
     * The cost breakdown, largest share first, for the chart on the calculator.
     *
     * @return list<array{key: string, label: string, amount: float, share: float}>
     */
    public function breakdown(): array
    {
        $total = $this->totalCost();

        $rows = [
            ['key' => 'fuel', 'label' => 'Combustibil', 'amount' => $this->fuelCost()],
            ['key' => 'salary', 'label' => 'Salariu șofer', 'amount' => $this->driverDaysPay()],
            ['key' => 'loading', 'label' => 'Zi încărcare retur', 'amount' => $this->loadingDayBonus],
            ['key' => 'wear', 'label' => 'Uzură vehicul', 'amount' => $this->wearCost()],
            ['key' => 'customs', 'label' => 'Vamă', 'amount' => $this->customs],
            ['key' => 'vignette', 'label' => 'Rovinietă', 'amount' => $this->vignette],
            ['key' => 'other', 'label' => 'Alte costuri', 'amount' => $this->otherCosts],
        ];

        $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['amount'] > 0.0));

        usort($rows, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return array_map(
            static fn (array $row): array => $row + ['share' => $total > 0.0 ? $row['amount'] / $total * 100 : 0.0],
            $rows,
        );
    }

    private function perKm(float $amount): float
    {
        $km = $this->totalDistanceKm();

        return $km > 0.0 ? $amount / $km : 0.0;
    }
}
