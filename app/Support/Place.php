<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RouteCalculation;
use Illuminate\Support\Str;

/**
 * The place names the fleet has actually driven to.
 *
 * There is no table of towns: the list is whatever the saved routes mention, so a
 * name earns its place the first time a route using it is saved and disappears
 * with the last route that used it. Nothing to maintain, and no invented
 * geography.
 *
 * Its job is to stop the same town being typed two ways. "Chișinău" and
 * "Chisinau" are one place, and once either spelling is on file the other snaps
 * to it — otherwise a search for one misses the routes filed under the other, and
 * the report counts them as two destinations.
 */
final class Place
{
    /** The columns that hold a place name. */
    private const COLUMNS = ['origin', 'destination', 'return_destination'];

    /**
     * Memo for the request. canonical() is called once per field and the list is
     * three queries; the form has three place fields.
     *
     * @var list<string>|null
     */
    private static ?array $memo = null;

    /**
     * Every place a saved route mentions, alphabetically, each spelt the way it
     * is stored.
     *
     * @return list<string>
     */
    public static function known(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $names = collect(self::COLUMNS)
            ->flatMap(static fn (string $column): array => RouteCalculation::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column)
                ->all())
            ->map(static fn (string $name): string => trim($name))
            ->filter();

        /*
         * Unique by folded key rather than by literal string. Two spellings of one
         * town would otherwise both survive into the suggestions, which is the
         * problem this class exists to prevent — the first one seen wins, and
         * canonical() then pulls the rest onto it.
         */
        return self::$memo = $names
            ->unique(static fn (string $name): string => self::key($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * What a typed name should be stored as: the spelling already on file when
     * one matches, and the tidied-up input when none does.
     */
    public static function canonical(?string $typed): string
    {
        $typed = trim(preg_replace('/\s+/u', ' ', (string) $typed) ?? '');

        if ($typed === '') {
            return '';
        }

        $key = self::key($typed);

        foreach (self::known() as $known) {
            if (self::key($known) === $key) {
                return $known;
            }
        }

        return $typed;
    }

    /**
     * Drop the memo. Saving a route can add a name, and the suggestions on the
     * next form have to include it.
     */
    public static function forget(): void
    {
        self::$memo = null;
    }

    /**
     * The form a name is compared in: no diacritics, no case. "Chișinău",
     * "chisinau" and "CHIȘINĂU" all reduce to the same key.
     */
    private static function key(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }
}
