<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * Number formatting for the screens.
 *
 * Romanian convention throughout: comma for the decimal mark, dot for the
 * thousands separator. The lei symbol comes from Settings → Valori implicite, so
 * a fleet settling in another currency is one field away; the quote symbol comes
 * from the route, because a customer may be billed in euro or in dollars.
 */
final class Format
{
    public static function money(float $amount, int $decimals = 2): string
    {
        return self::number($amount, $decimals).' '.Setting::currency();
    }

    /**
     * A sum in the currency a route was quoted in.
     *
     * Takes the currency rather than assuming euro: some customers pay in
     * dollars, and the symbol has to follow the route rather than the app.
     */
    public static function quoted(float $amount, string $currency = 'EUR', int $decimals = 2): string
    {
        return self::number($amount, $decimals).' '.Setting::quoteSymbol($currency);
    }

    public static function number(float $amount, int $decimals = 2): string
    {
        return number_format($amount, $decimals, ',', '.');
    }

    public static function perKm(float $amount, int $decimals = 2): string
    {
        return self::number($amount, $decimals).' '.Setting::currency().'/km';
    }

    public static function litres(float $amount): string
    {
        return self::number($amount, 0).' L';
    }

    public static function km(float $amount): string
    {
        return self::number($amount, 0).' km';
    }

    public static function percent(float $value, int $decimals = 1): string
    {
        return self::number($value, $decimals).'%';
    }

    /**
     * Consumption is stored per kilometre, because that is how the source
     * spreadsheet expresses it — but L/100km is what a driver quotes, so the
     * screens show both.
     */
    public static function per100km(float $litresPerKm): string
    {
        return self::number($litresPerKm * 100, 1).' L/100km';
    }
}
