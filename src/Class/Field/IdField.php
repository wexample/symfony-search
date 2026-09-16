<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** An identifier: pasted whole, matched exactly, worth little. */
class IdField extends AbstractField
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
}
