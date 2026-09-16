<?php

namespace Wexample\SymfonySearch\Class\Provider;

use Doctrine\ORM\QueryBuilder;
use RuntimeException;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Service\EntitySearchRunner;
use Wexample\SymfonySearch\Service\SearchableRegistry;

/**
 * One entity, searched with a reason.
 *
 * The road to take when what an entity answers depends on who is asking — the
 * legacy's `UserSearchService` restricted the list to other members, or to the
 * user alone, and no field list could have said that. Everything else still
 * comes from `#[Searchable]`, which the entity keeps carrying with
 * `provider: true` on it: the fields, the title, the route and the weight are
 * declared once whoever reads them.
 *
 * Subclasses name their entity and override `configureQuery()`.
 */
abstract class AbstractEntitySearchProvider extends AbstractSearchProvider
{
    public function __construct(
        protected readonly SearchableRegistry $registry,
        protected readonly EntitySearchRunner $runner,
    ) {
    }

    /** @return class-string */
    abstract public static function getEntityClassName(): string;

    /**
     * The entity's own name, not the provider's: a client asking for `invoice`
     * must reach this provider exactly as it would have reached the generic one.
     */
    public function getKey(): string
    {
        return ClassHelper::getTableizedName(static::getEntityClassName());
    }

    public function supports(SearchQuery $query): bool
    {
        if ($query->isEmpty() || ! $query->acceptsType($this->getKey())) {
            return false;
        }

        return $this->getSearchableEntity()->isInContext($query->context);
    }

    public function provide(SearchQuery $query): iterable
    {
        return $this->runner->run(
            $this->getSearchableEntity(),
            $query,
            $this->configureQuery(...)
        );
    }

    /**
     * Narrow the query before it runs. The builder already carries the `LIKE`
     * over the declared fields and its alias is `EntitySearchRunner::ALIAS`.
     */
    protected function configureQuery(
        QueryBuilder $builder,
        SearchQuery $query
    ): void {
    }

    protected function getSearchableEntity(): SearchableEntity
    {
        $searchableEntity = $this->registry->get($this->getKey());

        if (! $searchableEntity instanceof SearchableEntity) {
            // The provider exists, the attribute does not: the fields it is
            // about to search were never declared, and an empty result would
            // look like "nothing matched" rather than "nothing was declared".
            throw new RuntimeException(
                sprintf(
                    'Entity "%s" is searched by %s but carries no #[Searchable] attribute.',
                    static::getEntityClassName(),
                    static::class
                )
            );
        }

        return $searchableEntity;
    }
}
