<?php

namespace Wexample\SymfonySearch\Class;

use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonySearch\Attribute\Searchable;

/**
 * One entity class and what its attribute said about it, resolved once.
 *
 * The pair travels together because neither is usable alone: the attribute
 * names fields without saying whose, and the class says nothing about being
 * findable.
 */
final readonly class SearchableEntity
{
    public function __construct(
        /** @var class-string */
        public string $className,
        public Searchable $searchable,
    ) {
    }

    /**
     * The name this kind of result answers to, on the wire and in a query's
     * `type` — `invoice` for `App\Entity\Invoice`.
     */
    public function getType(): string
    {
        return ClassHelper::getTableizedName($this->className);
    }

    /**
     * The class saying what a record of this entity is worth, or null when
     * the declared fields say it all.
     *
     * @return class-string|null
     */
    public function getScoringClass(): ?string
    {
        return $this->searchable->scoring;
    }

    /** Whether a provider written by hand answers for this entity. */
    public function hasOwnProvider(): bool
    {
        return $this->searchable->provider;
    }

    public function isInContext(string $context): bool
    {
        return [] === $this->searchable->contexts
            || in_array($context, $this->searchable->contexts, true);
    }
}
