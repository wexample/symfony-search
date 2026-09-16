<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** A title, a name, a description: read, not keyed. */
class TextField extends AbstractField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison {
        if ($query->isNumeric()) {
            return null;
        }

        return $this->lowerLike($builder, $alias, $query, $parameter);
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->text(null === $value ? null : (string) $value, $this->points ?? SearchScore::POINTS_TEXT);
    }
}
