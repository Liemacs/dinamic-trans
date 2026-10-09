<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * The official exchange rates of the National Bank of Moldova.
 *
 * BNM publishes one XML document per day at
 * https://www.bnm.md/en/official_exchange_rates?get_xml=1&date=DD.MM.YYYY, and
 * this reads the euro and the dollar out of it.
 *
 * What the feed does, measured rather than assumed:
 *
 *   - A weekend or a holiday answers 200 with the last working day's rate carried
 *     forward, so there is nothing special to do for one.
 *   - A date not yet published — anything past the next working day — answers
 *     404 with an empty body.
 *   - `Nominal` is not always 1: the Albanian lek and the Armenian dram are quoted
 *     per 10. The euro and the dollar are per 1, but the rate is divided by it
 *     anyway, so that the day BNM changes it this does not quietly go wrong.
 *
 * A published rate for a date never changes, so a successful answer is cached
 * for good. A failed one is not — but it is remembered for a short while, so a
 * BNM outage costs one slow request rather than one on every page that asks.
 *
 * Nothing here ever throws to the caller. Every failure comes back as null, and
 * the caller falls back to the last rate it knew.
 */
final class BnmRates
{
    private const URL = 'https://www.bnm.md/en/official_exchange_rates';

    /** The currencies read out of the feed. */
    private const CURRENCIES = ['EUR', 'USD'];

    /** How long a failure is remembered before BNM is asked again. */
    private const FAILURE_COOLDOWN_MINUTES = 30;

    /** BNM is in Chișinău, and "today" is its today, not UTC's. */
    public const TIMEZONE = 'Europe/Chisinau';

    /**
     * The rates BNM set for a day, or null if they could not be had.
     *
     * @return array{date: CarbonImmutable, EUR: float, USD: float}|null
     */
    public static function for(CarbonInterface $day): ?array
    {
        $day = CarbonImmutable::parse($day->format('Y-m-d'), self::TIMEZONE);
        $key = 'bnm:rates:'.$day->format('Y-m-d');

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return self::hydrate($cached);
        }

        if (Cache::has($key.':failed')) {
            return null;
        }

        $rates = self::fetch($day);

        if ($rates === null) {
            Cache::put($key.':failed', true, now()->addMinutes(self::FAILURE_COOLDOWN_MINUTES));

            return null;
        }

        Cache::forever($key, [
            'date' => $rates['date']->format('Y-m-d'),
            'EUR' => $rates['EUR'],
            'USD' => $rates['USD'],
        ]);

        return $rates;
    }

    /** Today's rates, Chișinău's today. */
    public static function today(): ?array
    {
        return self::for(CarbonImmutable::now(self::TIMEZONE));
    }

    /**
     * Copy today's BNM rates into the settings, so that every new route starts
     * from them and the Valori implicite screen shows them.
     *
     * Only when the settings are behind: a rate already taken from BNM today, or
     * corrected by hand today, is left alone. A manual correction therefore holds
     * for the rest of the day and BNM takes over again tomorrow.
     *
     * @return bool whether the settings now hold today's BNM rates
     */
    public static function refreshSettings(bool $force = false): bool
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->format('Y-m-d');

        if (! $force && Setting::get('rates_date') === $today) {
            return Setting::get('rates_source') === 'bnm';
        }

        $rates = self::today();

        if ($rates === null) {
            return false;
        }

        Setting::put([
            'eur_rate' => (string) $rates['EUR'],
            'usd_rate' => (string) $rates['USD'],
            'rates_date' => $rates['date']->format('Y-m-d'),
            'rates_source' => 'bnm',
        ]);

        return true;
    }

    /**
     * Where the rates in the settings came from, for the line under a rate field.
     *
     * @return array{source: string, date: ?CarbonImmutable, current: bool}
     */
    public static function provenance(): array
    {
        $date = Setting::get('rates_date');
        $parsed = is_string($date) && $date !== ''
            ? CarbonImmutable::parse($date, self::TIMEZONE)
            : null;

        return [
            'source' => (string) (Setting::get('rates_source') ?: 'manual'),
            'date' => $parsed,
            'current' => $parsed?->isSameDay(CarbonImmutable::now(self::TIMEZONE)) ?? false,
        ];
    }

    /**
     * @return array{date: CarbonImmutable, EUR: float, USD: float}|null
     */
    private static function fetch(CarbonImmutable $day): ?array
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->accept('application/xml')
                ->get(self::URL, ['get_xml' => 1, 'date' => $day->format('d.m.Y')]);
        } catch (Throwable) {
            // Unreachable, timed out, DNS — all the same to the caller.
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return self::parse($response->body());
    }

    /**
     * The euro and the dollar out of one BNM document, or null if it is not one.
     *
     * Public so the parser can be held to the real document in a test.
     *
     * @return array{date: CarbonImmutable, EUR: float, USD: float}|null
     */
    public static function parse(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }

        /*
         * LIBXML_NONET keeps the parser off the network, and LIBXML_NOENT is
         * deliberately absent: a document from outside must not get to expand
         * entities. libxml's own errors are collected rather than printed, since
         * a malformed answer is an expected failure here, not a crash.
         */
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $document instanceof SimpleXMLElement || $document->getName() !== 'ValCurs') {
            return null;
        }

        // The date the rates are for, as BNM states it — not the one asked for.
        $date = CarbonImmutable::createFromFormat('d.m.Y', (string) $document['Date'], self::TIMEZONE);

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        $rates = ['date' => $date->startOfDay()];

        foreach ($document->Valute as $valute) {
            $code = strtoupper(trim((string) $valute->CharCode));

            if (! in_array($code, self::CURRENCIES, true)) {
                continue;
            }

            $value = (float) str_replace(',', '.', (string) $valute->Value);
            $nominal = (int) $valute->Nominal ?: 1;
            $rate = $value / $nominal;

            // A sanity bound, not a forecast: it only has to catch garbage.
            if ($rate <= 0.0 || $rate >= 1000.0) {
                return null;
            }

            $rates[$code] = round($rate, 4);
        }

        foreach (self::CURRENCIES as $code) {
            if (! isset($rates[$code])) {
                return null;
            }
        }

        return $rates;
    }

    /**
     * @param  array{date: string, EUR: float, USD: float}  $cached
     * @return array{date: CarbonImmutable, EUR: float, USD: float}
     */
    private static function hydrate(array $cached): array
    {
        return [
            'date' => CarbonImmutable::parse($cached['date'], self::TIMEZONE),
            'EUR' => (float) $cached['EUR'],
            'USD' => (float) $cached['USD'],
        ];
    }
}
