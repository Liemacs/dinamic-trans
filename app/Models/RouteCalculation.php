<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\RouteCosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCalculation extends Model
{
    protected $fillable = [
        'origin',
        'destination',
        'return_destination',
        'vehicle_id',
        'distance_km',
        'return_distance_km',
        'tonnes',
        'return_tonnes',
        'price_per_tonne',
        'return_price_per_tonne',
        'eur_rate',
        'consumption',
        'fuel_price',
        'wear_per_km',
        'customs',
        'vignette',
        'other_costs',
        'days',
        'driver_first_day',
        'driver_extra_day',
        'loading_day_bonus',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'return_distance_km' => 'float',
            'tonnes' => 'float',
            'return_tonnes' => 'float',
            'price_per_tonne' => 'float',
            'return_price_per_tonne' => 'float',
            'eur_rate' => 'float',
            'consumption' => 'float',
            'fuel_price' => 'float',
            'wear_per_km' => 'float',
            'customs' => 'float',
            'vignette' => 'float',
            'other_costs' => 'float',
            'days' => 'integer',
            'driver_first_day' => 'float',
            'driver_extra_day' => 'float',
            'loading_day_bonus' => 'float',
        ];
    }

    /**
     * The one definition of "matches the search box": the three place names and
     * the truck, by name or by plate.
     *
     * Shared by the list screen and the spreadsheet export, so the file someone
     * downloads holds exactly the rows they were looking at.
     *
     * The whole OR set sits inside its own closure. Chained loose onto the query,
     * a vehicle clause would widen the result rather than narrow it — and the
     * search would quietly return everything.
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        if (trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('origin', 'like', $like)
                ->orWhere('destination', 'like', $like)
                ->orWhere('return_destination', 'like', $like)
                ->orWhereHas('vehicle', function (Builder $vehicle) use ($like): void {
                    $vehicle->where('name', 'like', $like)
                        ->orWhere('plate', 'like', $like);
                });
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The derived figures — revenue, cost, profit, margin, per-kilometre rates.
     *
     * Computed from this row's own columns rather than stored alongside them:
     * one source of truth shared with the live calculator
     * (App\Support\RouteCosting), and no denormalised copy to fall out of step
     * when a row is edited.
     */
    public function costing(): RouteCosting
    {
        /*
         * Coerced rather than passed straight through. Every one of these columns
         * has a database default, but a model built by create() without naming
         * one holds null for it until it is read back — and RouteCosting takes
         * floats, so the round trip would decide whether this throws.
         */
        $number = fn (string $column): float => (float) ($this->{$column} ?? 0);

        return new RouteCosting(
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
            days: (int) ($this->days ?? 0),
            driverFirstDay: $number('driver_first_day'),
            driverExtraDay: $number('driver_extra_day'),
            returnDistanceKm: $number('return_distance_km'),
            returnTonnes: $number('return_tonnes'),
            returnPricePerTonne: $number('return_price_per_tonne'),
            loadingDayBonus: $number('loading_day_bonus'),
        );
    }

    /**
     * The run as a line of text. A return load adds its pickup point, because
     * "Hâncești → Brăila" and "Hâncești → Brăila → Galați" are different journeys
     * at different prices.
     */
    public function label(): string
    {
        $label = "{$this->origin} → {$this->destination}";

        return $this->return_destination
            ? $label." → {$this->return_destination}"
            : $label;
    }
}
