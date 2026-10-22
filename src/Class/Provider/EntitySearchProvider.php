<?php

namespace Wexample\SymfonySearch\Class\Provider;

use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Service\EntitySearchRunner;
use Wexample\SymfonySearch\Service\SearchableRegistry;

/**
 * Every entity carrying `#[Searchable]` and nothing else.
 *
 * One provider for all of them rather than one per entity: what differed
 * between the legacy's seven services was a field list, and a field list is
 * data. The entity that declared `provider: true` is left out — it has one of
 * its own, and both answering would show every one of its records twice.
 */
class EntitySearchProvider extends AbstractSearchProvider
{
    public function __construct(
        private readonly SearchableRegistry $registry,
        private readonly EntitySearchRunner $runner,
    ) {
    }

    public function supports(SearchQuery $query): bool
    {
        return ! $query->isEmpty() && [] !== $this->getMatching($query);
    }

    public function provide(SearchQuery $query): iterable
    {
        foreach ($this->getMatching($query) as $searchableEntity) {
            yield from $this->runner->run($searchableEntity, $query);
        }
    }

    /**
     * The searchable entities this query is actually asking about.
     *
     * @return array<SearchableEntity>
     */
    private function getMatching(SearchQuery $query): array
    {
        return array_values(
            array_filter(
                $this->registry->all(),
                static fn (SearchableEntity $entity): bool => ! $entity->hasOwnProvider()
                    && $query->acceptsType($entity->getType())
                    && $entity->isInContext($query->context)
            )
        );
    }
}
