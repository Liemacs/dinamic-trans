<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The defaults every new calculation starts from — fuel price, consumption,
 * driver allowance, fixed cost per kilometre, currency.
 *
 * A key/value table rather than a one-row settings model: adding a default costs
 * a line in self::defaults() and nothing else.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * What a fresh install runs on, and the fallback for any key never saved.
     *
     * The figures are the "PARAMETRI" block of the source spreadsheet
     * (Calculator_rute.xlsx, sheet "Rute", cells T2:T9).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Costs and results are in lei; only the customer's price is quoted
            // in euro, and eur_rate is what settles it.
            'currency' => 'MDL',
            'eur_rate' => '19.9',

            'consumption' => '0.45',    // litres per kilometre, not per 100
            'fuel_price' => '32',       // lei per litre
            'wear_per_km' => '2',       // fallback when a route has no vehicle

            'customs' => '700',
            'vignette' => '600',

            'driver_first_day' => '2200',
            'driver_extra_day' => '1000',
            // Paid on top of the day rate, for the day spent loading a return
            // load. Zero on a route that comes home empty.
            'loading_day_bonus' => '1200',

            // What a new vehicle starts with; each truck can carry its own.
            'gps_monthly' => '400',
        ];
    }

    /**
     * The container key the resolved set is memoised under for the request.
     */
    private const MEMO = 'settings.resolved';

    /**
     * Every setting, stored values over defaults.
     *
     * Memoised on the container rather than in a static: the cache store is the
     * database by default, so without a memo every formatted figure on a page —
     * and there are dozens — costs its own query for the currency symbol. The
     * container is rebuilt per request and per test, which a static property is
     * not: one would carry a test's settings into the next test in the run.
     *
     * Named values() rather than all() so Eloquent's own static all() stays where
     * it is.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        if (! app()->bound(self::MEMO)) {
            app()->instance(self::MEMO, Cache::rememberForever('settings', static fn (): array => array_merge(
                self::defaults(),
                self::query()->pluck('value', 'key')->all(),
            )));
        }

        return app()->get(self::MEMO);
    }

    public static function get(string $key, ?string $fallback = null): ?string
    {
        return self::values()[$key] ?? $fallback ?? self::defaults()[$key] ?? null;
    }

    public static function number(string $key): float
    {
        return (float) self::get($key);
    }

    public static function currency(): string
    {
        return (string) self::get('currency');
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget('settings');
        app()->forgetInstance(self::MEMO);
    }
}
