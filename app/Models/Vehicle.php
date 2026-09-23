<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Names;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'plate',
        'consumption',
        'insurance_annual',
        'service_annual',
        'tyres_annual',
        'other_annual',
        'gps_monthly',
        'annual_km',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'consumption' => 'float',
            'insurance_annual' => 'float',
            'service_annual' => 'float',
            'tyres_annual' => 'float',
            'other_annual' => 'float',
            'gps_monthly' => 'float',
            'annual_km' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function routeCalculations(): HasMany
    {
        return $this->hasMany(RouteCalculation::class);
    }

    /**
     * The model names already on the fleet, offered as suggestions on the vehicle
     * form.
     *
     * A fleet usually runs several of the same lorry — three Volvo FH 460 told
     * apart by their plates — so the name is not unique and repeating it is
     * normal. What is not normal is repeating it differently: "Volvo FH 460" and
     * "volvo fh460" read as two models in a list meant to be scanned.
     *
     * @return list<string>
     */
    public static function knownNames(): array
    {
        return Names::suggestions(
            self::query()->whereNotNull('name')->where('name', '!=', '')->distinct()->pluck('name')
        );
    }

    /**
     * What a typed model name should be stored as: the spelling already on the
     * fleet when one matches, the tidied input otherwise.
     *
     * Only the name. A plate is each lorry's own and must never be snapped onto
     * another's.
     */
    public static function canonicalName(?string $typed): string
    {
        return Names::canonical($typed, self::knownNames());
    }

    public function label(): string
    {
        return $this->plate ? "{$this->name} · {$this->plate}" : $this->name;
    }

    /**
     * The GPS billed for a year. It is a subscription, so it is entered per
     * month and annualised here rather than being stored twice.
     */
    public function gpsAnnual(): float
    {
        return $this->gps_monthly * 12;
    }

    /**
     * Everything the truck costs in a year, whatever cadence the bill arrives on.
     *
     * A record of what the fleet costs, not an input to any route: the wear a
     * route is charged is the flat rate in Settings and nothing here divides into
     * it.
     */
    public function annualTotal(): float
    {
        return $this->insurance_annual
            + $this->service_annual
            + $this->tyres_annual
            + $this->other_annual
            + $this->gpsAnnual();
    }

    public function monthlyTotal(): float
    {
        return $this->annualTotal() / 12;
    }

    /**
     * The annual expenses one line at a time, for the breakdown on the fleet
     * screen. Empty lines are kept: a zero for insurance is a fact worth seeing,
     * unlike a zero in a cost chart.
     *
     * @return list<array{key: string, label: string, annual: float, note: string|null}>
     */
    public function expenseLines(): array
    {
        return [
            ['key' => 'insurance', 'label' => 'Asigurare', 'annual' => $this->insurance_annual, 'note' => null],
            ['key' => 'service', 'label' => 'Service', 'annual' => $this->service_annual, 'note' => null],
            ['key' => 'tyres', 'label' => 'Anvelope', 'annual' => $this->tyres_annual, 'note' => null],
            ['key' => 'other', 'label' => 'Alte cheltuieli', 'annual' => $this->other_annual, 'note' => null],
            ['key' => 'gps', 'label' => 'GPS', 'annual' => $this->gpsAnnual(), 'note' => 'abonament lunar'],
        ];
    }
}
