<?php

namespace Wexample\SymfonySearch\Service;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Interface\SearchScoringInterface;

/**
 * Searching one entity from what its attribute declared.
 *
 * Held apart from the providers because two of them need it and neither owns
 * it: the generic provider runs it over every entity that declared no provider
 * of its own, and a hand-written provider runs it over its single entity after
 * having narrowed the query.
 *
 * Filtering is SQL and ranking is PHP, as decided: each field adds the clause
 * its kind allows for the query's shape, the rows come back, and each is scored
 * through the same builder — by the declared fields, or by the scoring class
 * the attribute names.
 */
class EntitySearchRunner
{
    /**
     * How many rows are read for each row shown.
     *
     * The score is computed after the query, so the query cannot order by it:
     * asking the database for exactly what is shown would mean showing the
     * first rows it happened to return rather than the best ones. Two is the
     * legacy's figure — a widening of the window, not a fix for it.
     */
    public const int FETCH_FACTOR = 2;

    public const string ALIAS = 'e';

    private const string PARAMETER_PREFIX = 'search_';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[AutowireLocator(SearchScoringInterface::TAG)]
        private readonly ContainerInterface $scorings,
    ) {
    }

    /**
     * @param Closure(QueryBuilder, SearchQuery): void|null $configureQuery
     *
     * @return iterable<SearchResult>
     */
    public function run(
        SearchableEntity $searchableEntity,
        SearchQuery $query,
        ?Closure $configureQuery = null,
    ): iterable {
        $builder = $this->createQueryBuilder($searchableEntity, $query);

        // No field could be asked this shape of query: nothing to fetch, and
        // an unconstrained query would have returned the table.
        if (null === $builder) {
            return;
        }

        if ($configureQuery instanceof Closure) {
            $configureQuery($builder, $query);
        }

        foreach ($builder->getQuery()->getResult() as $entity) {
            yield $this->createResult($searchableEntity, $entity, $query);
        }
    }

    /**
     * The query over the declared fields, or null when none of them can be
     * looked for what was typed.
     */
    public function createQueryBuilder(
        SearchableEntity $searchableEntity,
        SearchQuery $query
    ): ?QueryBuilder {
        $builder = $this
            ->entityManager
            ->createQueryBuilder()
            ->select(self::ALIAS)
            ->from($searchableEntity->className, self::ALIAS);

        $orX = $builder->expr()->orX();

        foreach ($searchableEntity->searchable->fields as $index => $field) {
            $clause = $field->constrain($builder, self::ALIAS, $query, self::PARAMETER_PREFIX.$index);

            if (null !== $clause) {
                $orX->add($clause);
            }
        }

        if (0 === $orX->count()) {
            return null;
        }

        return $builder
            ->where($orX)
            ->setMaxResults($query->maxResults * self::FETCH_FACTOR);
    }

    private function createResult(
        SearchableEntity $searchableEntity,
        AbstractEntity $entity,
        SearchQuery $query
    ): SearchResult {
        $searchable = $searchableEntity->searchable;

        $result = new SearchResult(
            // The type of an entity result is the entity, not whichever
            // provider found it: a client asking for `invoice` gets invoices
            // and never learns who answered.
            $searchableEntity->getType(),
            (string) $entity->getId(),
            (string) ClassHelper::getFieldGetterValueOrDefault(
                $entity,
                $searchable->getTitleField(),
                ''
            )
        );

        $result
            ->setScore($this->score($searchableEntity, $entity, $query))
            ->setIcon($searchable->icon);

        if ($searchable->subtitleField) {
            $result->setSubtitle(
                ClassHelper::getFieldGetterValueOrDefault(
                    $entity,
                    $searchable->subtitleField
                )
            );
        }

        if ($searchable->route) {
            $result->setUrl(
                $this->urlGenerator->generate(
                    $searchable->route,
                    ['id' => (string) $entity->getId()]
                )
            );
        }

        return $result;
    }

    /**
     * The declared fields, said to the builder one by one — unless the
     * attribute names a class, which then says it all.
     */
    private function score(
        SearchableEntity $searchableEntity,
        AbstractEntity $entity,
        SearchQuery $query
    ): float {
        $score = new SearchScore($query);
        $scoringClass = $searchableEntity->getScoringClass();

        if (null !== $scoringClass) {
            /** @var SearchScoringInterface $scoring */
            $scoring = $this->scorings->get($scoringClass);
            $scoring->score($query, $entity, $score);

            return $score->getPoints();
        }

        foreach ($searchableEntity->searchable->fields as $field) {
            $field->score(
                $score,
                ClassHelper::getFieldGetterValueOrDefault($entity, $field->name)
            );
        }

        return $score->getPoints();
    }
}
