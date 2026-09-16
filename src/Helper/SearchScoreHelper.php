<?php

namespace Wexample\SymfonySearch\Helper;

use function filter_var;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_starts_with;
use function strtolower;

use Wexample\Helpers\Helper\TextHelper;

/**
 * How well a string answers a search, in points.
 *
 * Ranking is not filtering. SQL decides which rows come back; this decides in
 * which order they are shown, and it runs in PHP on the handful of rows that
 * came back. Doing it in the query would mean expressing "the needle is a whole
 * word, near the start, accents aside" in joins — which is the shape the legacy
 * avoided, and rightly.
 */
class SearchScoreHelper
{
    public const POINTS_BASE_DEFAULT = 10;

    /** An email in the query is a deliberate act, so it outweighs the rest. */
    public const POINTS_BASE_EMAIL = 30;

    /** The haystack is the needle and nothing else. */
    public const MULTIPLIER_EQUAL = 4;

    /** The same, up to the case — worth less than the real thing, not much. */
    public const MULTIPLIER_EQUAL_OTHER_CASE = 2;

    public const MULTIPLIER_AT_START = 1.5;

    public const MULTIPLIER_WHOLE_WORD = 1.5;

    /** Accents had to be dropped for the two to meet, which costs a tenth. */
    public const MULTIPLIER_SLUGIFIED = .9;

    /**
     * Three ways two strings can be made to meet, tried in order: as they were
     * written, ignoring case, then ignoring accents. The first that answers
     * wins — a match found late is not added to one found early, it replaces it.
     */
    public static function stringScore(
        ?string $needle,
        ?string $haystack,
        float $pointsBase = self::POINTS_BASE_DEFAULT
    ): float {
        if (! $needle || ! $haystack) {
            return 0;
        }

        $score = self::scoreAtLevel(
            $needle,
            $haystack,
            $pointsBase,
            self::MULTIPLIER_EQUAL
        );

        if ($score > 0) {
            return $score;
        }

        // Typing the wrong case is the common mistake rather than a different
        // word, so containment costs nothing here and only equality is worth
        // less than it would have been written exactly.
        $needle = strtolower($needle);
        $haystack = strtolower($haystack);

        $score = self::scoreAtLevel(
            $needle,
            $haystack,
            $pointsBase,
            self::MULTIPLIER_EQUAL_OTHER_CASE
        );

        if ($score > 0) {
            return $score;
        }

        return self::scoreAtLevel(
            TextHelper::slugify($needle),
            TextHelper::slugify($haystack),
            $pointsBase * self::MULTIPLIER_SLUGIFIED,
            self::MULTIPLIER_EQUAL
        );
    }

    /**
     * What a match is worth once both strings have been brought to the same
     * form. Nothing in here looks at case or accents again.
     */
    private static function scoreAtLevel(
        string $needle,
        string $haystack,
        float $pointsBase,
        float $equalityMultiplier
    ): float {
        if ($needle === $haystack) {
            return $pointsBase * $equalityMultiplier;
        }

        if (! str_contains($haystack, $needle)) {
            return 0;
        }

        $score = $pointsBase;

        if (str_starts_with($haystack, $needle)) {
            $score *= self::MULTIPLIER_AT_START;
        }

        // Quoted, because the needle is what the user typed: the legacy
        // interpolated it raw into the pattern, so a query containing `(`
        // scored nothing and emitted a warning.
        if (preg_match('/\b'.preg_quote($needle, '/').'\b/', $haystack)) {
            $score *= self::MULTIPLIER_WHOLE_WORD;
        }

        return $score;
    }

    /**
     * What an email field is worth, which is nothing unless the query is itself
     * an email — a fragment matching an address is a coincidence, a whole
     * address matching it is an intent.
     */
    public static function emailScore(
        string $terms,
        ?string $fieldValue
    ): float {
        if (! filter_var($terms, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        return self::stringScore(
            $terms,
            $fieldValue,
            self::POINTS_BASE_EMAIL
        );
    }

    /**
     * The best any of these fields can say about the query, summed — a record
     * matching on two fields answers better than one matching on a single.
     */
    public static function fieldsScore(
        string $terms,
        array $values,
        float $pointsBase = self::POINTS_BASE_DEFAULT
    ): float {
        $score = 0;

        foreach ($values as $value) {
            $score += self::stringScore($terms, $value, $pointsBase);
        }

        return $score;
    }
}
