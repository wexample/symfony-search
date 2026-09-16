<?php

namespace Wexample\SymfonySearch\Class;

use BackedEnum;
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
         * Restricts the answer to one kind of result — an entity path such as
         * `invoice`, or the key of a provider. Null asks everyone.
         */
        public readonly ?string $type = null,
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

    /** Whether a provider answering with `$type` was asked anything. */
    public function acceptsType(string $type): bool
    {
        return null === $this->type || $this->type === $type;
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
            $this->type
        );
    }
}
