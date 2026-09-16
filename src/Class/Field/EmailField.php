<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** An address: found whole, or not at all. */
class EmailField extends AbstractField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison {
        if (! $query->isEmail()) {
            return null;
        }

        $builder->setParameter($parameter, mb_strtolower($query->terms));

        return $builder->expr()->eq('LOWER('.$alias.'.'.$this->name.')', ':'.$parameter);
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->email(null === $value ? null : (string) $value, $this->points ?? SearchScore::POINTS_EMAIL);
    }
}
