<?php

namespace Wexample\SymfonySearch\Class;

use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonySearch\Attribute\AbstractSearchField;
use Wexample\SymfonySearch\Attribute\Searchable;

/**
 * One entity class, what its attribute said about it, and the fields its
 * properties declared — resolved once by the registry.
 *
 * The three travel together because none is usable alone: the attribute says
 * nothing about which columns to look in, the properties say nothing about
 * where the entity may be found, and the class says nothing at all.
 */
final readonly class SearchableEntity
{
    public function __construct(
        /** @var class-string */
        public string $className,
        public Searchable $searchable,
        /** @var array<AbstractSearchField> in the order the properties declare them */
        public array $fields,
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
     * Which property is shown as the title. The attribute may name one; failing
     * that it is the first field declared, and failing that the entity is
     * findable on nothing and has no title to show.
     */
    public function getTitleField(): ?string
    {
        return $this->searchable->titleField ?? ($this->fields[0]->name ?? null);
    }

    /**
     * The class saying what a record of this entity is worth, or null when the
     * declared fields say it all.
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
