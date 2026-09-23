<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RouteCalculation;

/**
 * The place names the fleet has actually driven to.
 *
 * There is no table of towns: the list is whatever the saved routes mention, so a
 * name earns its place the first time a route using it is saved and disappears
 * with the last route that used it. Nothing to maintain, and no invented
 * geography.
 *
 * Its job is to stop the same town being typed two ways — see App\Support\Names,
 * which does the matching.
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
        return self::$memo ??= Names::suggestions(
            collect(self::COLUMNS)->flatMap(static fn (string $column): array => RouteCalculation::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column)
                ->all())
        );
    }

    /**
     * What a typed name should be stored as: the spelling already on file when
     * one matches, and the tidied-up input when none does.
     */
    public static function canonical(?string $typed): string
    {
        return Names::canonical($typed, self::known());
    }

    /**
     * Drop the memo. Saving a route can add a name, and the suggestions on the
     * next form have to include it.
     */
    public static function forget(): void
    {
        self::$memo = null;
    }
}
