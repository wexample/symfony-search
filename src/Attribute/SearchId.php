<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** An identifier: pasted whole, matched exactly, worth little. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class SearchId extends AbstractSearchField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison {
        if ($query->isEmpty()) {
            return null;
        }

        $builder->setParameter($parameter, $query->terms);

        return $builder->expr()->eq($alias.'.'.$this->name, ':'.$parameter);
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->id(is_scalar($value) ? $value : null, $this->points ?? SearchScore::POINTS_ID);
    }

    public function getKindName(): string
    {
        return 'id';
    }
}
