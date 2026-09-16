<?php

namespace Wexample\SymfonySearch\Class\Field;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/**
 * A price, a total, a quantity: found by its figure, never by `LIKE`.
 *
 * Searched on its absolute value, as the legacy did: someone typing `150` is
 * looking for a hundred and fifty, whichever way the money went.
 */
class AmountField extends AbstractField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Comparison {
        $amount = $query->getAmount();

        if (null === $amount) {
            return null;
        }

        $builder->setParameter($parameter, abs($amount));

        return $builder->expr()->eq('ABS('.$alias.'.'.$this->name.')', ':'.$parameter);
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->amount(is_scalar($value) ? $value : null, $this->points ?? SearchScore::POINTS_AMOUNT);
    }
}
