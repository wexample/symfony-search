<?php

namespace Wexample\SymfonySearch\Service;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Helper\SearchScoreHelper;

/**
 * Searching one entity from what its attribute declared.
 *
 * Held apart from the providers because two of them need it and neither owns
 * it: the generic provider runs it over every entity that declared no provider
 * of its own, and a hand-written provider runs it over its single entity after
 * having narrowed the query.
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

    private const string PARAMETER_TERMS = 'terms';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
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

        if ($configureQuery instanceof Closure) {
            $configureQuery($builder, $query);
        }

        foreach ($builder->getQuery()->getResult() as $entity) {
            yield $this->createResult($searchableEntity, $entity, $query);
        }
    }

    public function createQueryBuilder(
        SearchableEntity $searchableEntity,
        SearchQuery $query
    ): QueryBuilder {
        $builder = $this
            ->entityManager
            ->createQueryBuilder()
            ->select(self::ALIAS)
            ->from($searchableEntity->className, self::ALIAS);

        $orX = $builder->expr()->orX();

        foreach ($searchableEntity->searchable->fields as $field) {
            $orX->add(
                $builder->expr()->like(
                    'LOWER('.self::ALIAS.'.'.$field.')',
                    ':'.self::PARAMETER_TERMS
                )
            );
        }

        return $builder
            ->where($orX)
            // Lowered on both sides rather than trusting the collation, which
            // is the database's business and differs between two of them.
            ->setParameter(
                self::PARAMETER_TERMS,
                '%'.mb_strtolower($query->terms).'%'
            )
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
            ->setScore(
                SearchScoreHelper::fieldsScore(
                    $query->terms,
                    $this->readFields($entity, $searchable->fields)
                ) * $searchable->weight
            )
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
     * @param array<string> $fields
     *
     * @return array<string|null>
     */
    private function readFields(
        AbstractEntity $entity,
        array $fields
    ): array {
        return array_map(
            static fn (string $field): ?string => ClassHelper::getFieldGetterValueOrDefault($entity, $field),
            $fields
        );
    }
}
