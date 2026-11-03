<?php

namespace Wexample\SymfonySearch\Class;

use BackedEnum;
use DateTimeImmutable;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonySearch\Enum\SearchContext;

/**
 * One question asked to the search, as it stands before any provider sees it.
 *
 * It is a value and not a request: the controller builds it from query options,
 * a form field builds it from its own configuration, and a test builds it from
 * nothing at all.
 *
 * It also knows its own shape — a number, an amount, an address — because that
 * is what decides which fields may answer. The legacy asked `is_numeric()` in
 * every entity service; here it is asked once, at the door.
 */
class SearchQuery
{
    /** Asks for as many results as the bundle is configured to return. */
    public const int MAX_RESULTS_UNSET = 0;

    public readonly string $terms;

    public readonly string $context;

    public function __construct(
        string $terms,
        BackedEnum|string $context = SearchContext::DEFAULT,
        public readonly int $maxResults = self::MAX_RESULTS_UNSET,
        /**
         * Restricts the answer to some kinds of result — an entity path such
         * as `invoice`, or the key of a provider; several separated by commas
         * (`project,scenario`). Null asks everyone.
         */
        public readonly ?string $type = null,
        /**
         * What narrows the answer besides the terms, by name — the record the
         * results belong to (`scenario` => its id). Read by the providers that
         * know the name; the others pass it by.
         *
         * @var array<string, string>
         */
        public readonly array $filters = [],
    ) {
        // Trimmed once, here, rather than in each provider: a query is what the
        // user typed, and the spaces around it are never part of it.
        $this->terms = trim($terms);

        // A context is a string on the wire and a case in the code. Held as the
        // string, so that an application's own enum needs no registration to be
        // understood — and so that the only conversion happens at this door.
        $this->context = $context instanceof BackedEnum
            ? (string) $context->value
            : $context;
    }

    /** Whether the query asks anything at all. */
    public function isEmpty(): bool
    {
        return '' === $this->terms;
    }

    // --- The shapes a query can have. A field answers only the shape it fits.

    /** `15428`, `12.5`, `-3` — a number and nothing else. */
    public function isNumeric(): bool
    {
        return is_numeric($this->terms);
    }

    /**
     * What the query is worth as a quantity, or null when it holds none:
     * `1 250,50 €` reads as 1250.5, `dupont` reads as nothing.
     */
    public function getAmount(): ?float
    {
        if (! preg_match('/\d/', $this->terms)) {
            return null;
        }

        return TextHelper::getFloatFromString($this->terms);
    }

    /**
     * The day the query names, or null when it names none: `2026-10-30`,
     * `30/10/2026`, `30.10.2026`, or `30/10` — this year's.
     */
    public function getDate(): ?DateTimeImmutable
    {
        foreach (['!Y-m-d', '!d/m/Y', '!d.m.Y', '!d/m'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $this->terms);

            // Read whole and as written: `31/02` is no day, not the 3rd of March.
            if (false !== $date && $date->format(ltrim($format, '!')) === $this->terms) {
                return '!d/m' === $format
                    ? $date->setDate((int) date('Y'), (int) $date->format('m'), (int) $date->format('d'))
                    : $date;
            }
        }

        return null;
    }

    /** A whole address, which is an intent rather than a fragment. */
    public function isEmail(): bool
    {
        return TextHelper::isEmail($this->terms);
    }

    public function isInContext(BackedEnum|string ...$contexts): bool
    {
        foreach ($contexts as $context) {
            $value = $context instanceof BackedEnum
                ? (string) $context->value
                : $context;

            if ($value === $this->context) {
                return true;
            }
        }

        return false;
    }

    /** The value a filter was given, or null when the query names none. */
    public function getFilter(string $name): ?string
    {
        $value = $this->filters[$name] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    /** Whether a provider answering with `$type` was asked anything. */
    public function acceptsType(string $type): bool
    {
        return null === $this->type || in_array($type, $this->getTypes(), true);
    }

    /**
     * The kinds asked, one per name the `type` lists — empty when it lists
     * none, which asks everyone.
     *
     * @return list<string>
     */
    public function getTypes(): array
    {
        return null === $this->type
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $this->type)), static fn (string $type): bool => '' !== $type));
    }

    /**
     * The same question with a number of results on it, which the service puts
     * there before any provider reads it — so that a provider capping its query
     * never has to know what the default was.
     */
    public function withMaxResults(int $maxResults): self
    {
        return new self(
            $this->terms,
            $this->context,
            $maxResults,
            $this->type,
            $this->filters
        );
    }
}
