<?php

namespace Wexample\SymfonySearch\Interface;

use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;

/**
 * How much one record answers a query, for the entity whose answer cannot be
 * a list of fields.
 *
 * Six of the legacy's seven scoring functions were the same eight lines around
 * different getters, and those are now the fields declared on `#[Searchable]`.
 * The seventh halved its points in one context and doubled them on one field —
 * that one is a class, named on the attribute, and it is given the same builder
 * the declared fields go through.
 */
interface SearchScoringInterface
{
    public const string TAG = 'wexample_symfony_search.scoring';

    public function score(
        SearchQuery $query,
        AbstractEntity $entity,
        SearchScore $score
    ): void;
}
