<?php

namespace Wexample\SymfonySearch\Class;

use BackedEnum;
use Wexample\SymfonySearch\Helper\SearchScoreHelper;

/**
 * The points one record earns, said one field at a time.
 *
 * Each method names what a field *is* — text, a reference, an amount, an
 * address, an id — and knows when such a field can answer at all: an amount
 * says nothing to `dupont`, a title says nothing to `15428`. The caller
 * declares the nature and the points; the builder keeps the guards, which the
 * legacy repeated as `if (!is_numeric(...))` in every service.
 *
 * Points add up across fields — a record matching on two answers better than
 * one matching on one — and a context factor applies to the total whenever it
 * was declared, first or last.
 */
class SearchScore
{
    public const float POINTS_TEXT = 10;

    /** A code someone knows by heart, so matching it is rarely a coincidence. */
    public const float POINTS_REFERENCE = 30;

    /** The figure that was typed on purpose. */
    public const float POINTS_AMOUNT = 40;

    public const float POINTS_EMAIL = 30;

    /** An id is pasted, not searched: worth something, not much. */
    public const float POINTS_ID = 5;

    private float $points = 0;

    private float $factor = 1;

    public function __construct(
        private readonly SearchQuery $query,
    ) {
    }

    /** A title, a name, a description: anything read rather than keyed. */
    public function text(
        ?string $value,
        float $points = self::POINTS_TEXT
    ): self {
        // A number typed in is not a word looked for.
        if (! $this->query->isNumeric()) {
            $this->points += SearchScoreHelper::stringScore($this->query->terms, $value, $points);
        }

        return $this;
    }

    /** A reference, a code, a serial — letters or digits, matched as typed. */
    public function reference(
        ?string $value,
        float $points = self::POINTS_REFERENCE
    ): self {
        $this->points += SearchScoreHelper::stringScore($this->query->terms, $value, $points);

        return $this;
    }

    /**
     * A price, a total, a quantity. Answers only a query holding a figure, and
     * the whole of the points when the figure is the same — `15428` finds the
     * invoice of 15428 before the log whose id happens to be 15428.
     */
    public function amount(
        float|int|string|null $value,
        float $points = self::POINTS_AMOUNT
    ): self {
        $asked = $this->query->getAmount();

        if (null === $asked || null === $value || '' === $value) {
            return $this;
        }

        if (abs((float) $value - $asked) < 0.005) {
            $this->points += $points;

            return $this;
        }

        // The legacy scored an amount as text, which is what lets `154` find
        // 15428: kept, at a fraction, so an exact figure still comes first.
        $this->points += SearchScoreHelper::stringScore(
            $this->query->terms,
            (string) $value,
            $points / 4
        );

        return $this;
    }

    /** Answers only a whole address: a fragment matching one is a coincidence. */
    public function email(
        ?string $value,
        float $points = self::POINTS_EMAIL
    ): self {
        if ($this->query->isEmail()) {
            $this->points += SearchScoreHelper::stringScore($this->query->terms, $value, $points);
        }

        return $this;
    }

    /** Exactly the id, or nothing. */
    public function id(
        int|string|null $value,
        float $points = self::POINTS_ID
    ): self {
        if (null !== $value && (string) $value === $this->query->terms) {
            $this->points += $points;
        }

        return $this;
    }

    /**
     * What this kind of record is worth where the search is made from — half
     * in a header that shows a bit of everything, whole in a form field asking
     * for exactly this.
     */
    public function inContext(
        BackedEnum|string $context,
        float $factor
    ): self {
        if ($this->query->isInContext($context)) {
            $this->factor *= $factor;
        }

        return $this;
    }

    public function getPoints(): float
    {
        return $this->points * $this->factor;
    }
}
