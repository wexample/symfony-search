<?php

namespace Wexample\SymfonySearch\Interface;

use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Entity\SearchResult;

/**
 * One family of findable things.
 *
 * Implementing it is the whole registration: the bundle autoconfigures the
 * interface, so a provider written in an application joins the search by
 * existing.
 */
interface SearchProviderInterface
{
    public const string TAG = 'wexample_symfony_search.provider';

    /**
     * What this provider answers with, as it appears in `SearchResult::$type`
     * and in the `type` a query may restrict itself to.
     */
    public function getKey(): string;

    /** Whether this query is worth asking this provider at all. */
    public function supports(SearchQuery $query): bool;

    /**
     * Everything found, scored, in no particular order — the service sorts.
     *
     * @return iterable<SearchResult>
     */
    public function provide(SearchQuery $query): iterable;
}
