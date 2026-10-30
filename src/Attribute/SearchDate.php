<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;
use DateTimeInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/** A date: a day typed (SearchQuery::getDate()) finds what falls on it. */
#[Attribute(Attribute::TARGET_PROPERTY)]
class SearchDate extends AbstractSearchField
{
    public function constrain(
        QueryBuilder $builder,
        string $alias,
        SearchQuery $query,
        string $parameter
    ): ?Andx {
        $day = $query->getDate();

        if (null === $day) {
            return null;
        }

        // The whole day, from its first instant to the next day's.
        $builder->setParameter($parameter.'_from', $day);
        $builder->setParameter($parameter.'_to', $day->modify('+1 day'));
        $field = $alias.'.'.$this->name;

        return $builder->expr()->andX(
            $builder->expr()->gte($field, ':'.$parameter.'_from'),
            $builder->expr()->lt($field, ':'.$parameter.'_to'),
        );
    }

    public function score(
        SearchScore $score,
        mixed $value
    ): void {
        $score->date($value instanceof DateTimeInterface ? $value : null, $this->points ?? SearchScore::POINTS_DATE);
    }

    public function getKindName(): string
    {
        return 'date';
    }
}
