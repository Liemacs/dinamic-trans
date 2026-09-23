<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Matching names the way a person would: ignoring diacritics, case and stray
 * spacing.
 *
 * "Chișinău" and "chisinau" are one town; "Volvo FH 460" and "volvo fh460" are
 * one lorry model. Stored as typed, the second spelling of either becomes a
 * separate thing — a search for one misses the other, and any grouping counts
 * them twice.
 *
 * Shared by App\Support\Place and App\Models\Vehicle, which each supply their own
 * list of what is already on file.
 */
final class Names
{
    /**
     * The form two names are compared in. "Chișinău", "chisinau" and "CHIȘINĂU"
     * all reduce to the same key.
     */
    public static function key(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }

    /** Trimmed, with runs of whitespace collapsed to one space. */
    public static function tidy(?string $typed): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $typed) ?? '');
    }

    /**
     * What a typed name should be stored as: the spelling already on file when
     * one matches it, and the tidied input when none does.
     *
     * @param  list<string>  $known
     */
    public static function canonical(?string $typed, array $known): string
    {
        $typed = self::tidy($typed);

        if ($typed === '') {
            return '';
        }

        $key = self::key($typed);

        foreach ($known as $name) {
            if (self::key($name) === $key) {
                return $name;
            }
        }

        return $typed;
    }

    /**
     * A suggestion list out of raw values: tidied, blanks dropped, one spelling
     * per name, alphabetical.
     *
     * Unique by folded key rather than by literal string — two spellings already
     * in the data would otherwise both appear, which is the confusion this is
     * meant to end. The first one seen wins, and canonical() pulls the rest onto
     * it.
     *
     * @param  iterable<string|null>  $names
     * @return list<string>
     */
    public static function suggestions(iterable $names): array
    {
        return collect($names)
            ->map(static fn (?string $name): string => self::tidy($name))
            ->filter()
            ->unique(static fn (string $name): string => self::key($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
