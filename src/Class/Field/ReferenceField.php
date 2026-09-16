<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** A code, a serial, an invoice number: letters or digits, matched as typed. */
class ReferenceField extends AbstractField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison {
        return $this->lowerLike($builder, $alias, $query, $parameter);
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->reference(null === $value ? null : (string) $value, $this->points ?? SearchScore::POINTS_REFERENCE);
    }
}
