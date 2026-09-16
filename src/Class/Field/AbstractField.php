<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/**
 * One property an entity can be found on, and what kind of thing it holds.
 *
 * The kind is the whole point. It decides the SQL — a title is searched with
 * `LIKE`, an amount with `=` — and the points, through the matching method of
 * the score builder. A field whose kind does not fit the query's shape adds no
 * clause and no points: an amount is not asked `dupont`.
 *
 * Declared in `#[Searchable]` with `new`, because an attribute argument can be
 * a constructor call and nothing more.
 */
abstract class AbstractField
{
    public function __construct(
        public readonly string $name,
        public readonly ?float $points = null,
    ) {
    }

    /**
     * The clause finding this field, or null when the query's shape cannot
     * be looked for in it.
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
