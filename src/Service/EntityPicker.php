<?php

namespace Wexample\SymfonySearch\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\Provider\AbstractEntitySearchProvider;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Interface\SearchProviderInterface;

/**
 * A record picked by searching for it, taken back from the id that was posted.
 *
 * The search offered it through a provider, in a context, to someone allowed to
 * search: the id is checked on that same road, so a form never takes a record
 * the person could not have been offered — the legacy's field found any id
 * that existed.
 */
class EntityPicker
{
    /**
     * @param iterable<SearchProviderInterface> $providers
     */
    public function __construct(
        private readonly SearchableRegistry $registry,
        private readonly EntitySearchRunner $runner,
        private readonly SearchService $searchService,
        #[AutowireIterator(SearchProviderInterface::TAG)]
        private readonly iterable $providers,
    ) {
    }

    public function find(
        SearchableEntity $searchableEntity,
        string $id,
        string $context
    ): ?AbstractEntity {
        if ('' === $id || ! $this->searchService->isGranted()) {
            return null;
        }

        $query = new SearchQuery('', $context, type: $searchableEntity->getType());

        if (! $searchableEntity->hasOwnProvider()) {
            return $this->runner->find($searchableEntity, $id, $query);
        }

        // The entity said a provider of its own answers for it: that one's
        // narrowing is what the person was offered, so it decides here too.
        foreach ($this->providers as $provider) {
            if ($provider instanceof AbstractEntitySearchProvider
                && $provider->getKey() === $searchableEntity->getType()) {
                return $provider->find($id, $query);
            }
        }

        return null;
    }

    /**
     * What a record is shown as when a field opens on it, or null for a
     * record of a class that is not findable.
     */
    public function describe(AbstractEntity $entity): ?SearchResult
    {
        $searchableEntity = $this->registry->getByClass($entity::class);

        return null === $searchableEntity
            ? null
            : $this->runner->describe($searchableEntity, $entity);
    }
}
