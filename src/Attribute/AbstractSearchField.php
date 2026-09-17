<?php

namespace Wexample\SymfonySearch\Attribute;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/**
 * A property an entity can be found on, and what kind of thing it holds.
 *
 * The kind is the whole point. It decides the SQL — a title is searched with
 * `LIKE`, an amount with `=` on its absolute value — and the points, through
 * the matching method of the score builder. A kind that does not fit the
 * query's shape adds no clause and no points: an amount is not asked `dupont`,
 * a title is not asked `15428`.
 *
 * Declared on the property rather than listed on the class, so that it travels
 * with a renaming, and so that a trait bringing a column can bring the way it
 * is searched with it.
 */
abstract class AbstractSearchField
{
    /**
     * The property carrying this attribute.
     *
     * Filled by the registry rather than by the constructor: an attribute has
     * no way of knowing what it was written on, and the registry reads it once
     * for the whole request.
     */
    public string $name = '';

    public function __construct(
        /** Null takes the kind's own default. */
        public readonly ?float $points = null,
    ) {
    }

    /**
     * The clause finding this field, or null when the query's shape cannot be
     * looked for in it.
     */
    abstract public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison;

    /** The points this field earns, said to the builder. */
    abstract public function score(
        SearchScore $score,
        mixed $value
    ): void;

    /** What the search page and the debug output call this kind. */
    abstract public function getKindName(): string;

    protected function lowerLike(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): Comparison {
        // Lowered on both sides rather than trusting the collation, which is
        // the database's business and differs between two of them.
        $builder->setParameter($parameter, '%'.mb_strtolower($query->terms).'%');

        return $builder->expr()->like('LOWER('.$alias.'.'.$this->name.')', ':'.$parameter);
    }
}
