<?php

namespace Wexample\SymfonySearch\Service;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Wexample\SymfonySearch\Attribute\AbstractSearchField;
use Wexample\SymfonySearch\Attribute\Searchable;
use Wexample\SymfonySearch\Attribute\SearchIgnore;
use Wexample\SymfonySearch\Class\SearchableEntity;

/**
 * Every entity that declared itself findable, and what each is findable on.
 *
 * Read from Doctrine's metadata rather than by walking directories: the mapping
 * already knows every entity the application has, wherever its bundle lives, and
 * an entity Doctrine does not know is one no query could have reached anyway.
 */
class SearchableRegistry
{
    /** @var array<string, SearchableEntity>|null by the type they answer to */
    private ?array $entities = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, SearchableEntity>
     */
    public function all(): array
    {
        // Metadata is read once per request. It costs a full reflection pass,
        // which is why nothing here is called per result.
        return $this->entities ??= $this->load();
    }

    public function get(string $type): ?SearchableEntity
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * @return array<string, SearchableEntity>
     */
    private function load(): array
    {
        $entities = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $reflection = $metadata->getReflectionClass();
            $attributes = $reflection->getAttributes(Searchable::class);

            if ([] === $attributes) {
                continue;
            }

            // Repeatable, but only the first is read: a second one means a
            // second way of searching the same entity, and that is a provider
            // written by hand rather than a line in an attribute.
            $searchable = $attributes[0]->newInstance();

            $entity = new SearchableEntity(
                $metadata->getName(),
                $searchable,
                $this->loadFields($reflection, $searchable)
            );

            $entities[$entity->getType()] = $entity;
        }

        return $entities;
    }

    /**
     * The fields the properties declare, in the order they are declared.
     *
     * `getProperties()` reports what a trait brought as if the class had written
     * it, so a trait carrying a column carries the way it is searched with it —
     * and an entity that would rather not redeclares the property with
     * `#[SearchIgnore]`, or names it in the attribute's `except`.
     *
     * @return array<AbstractSearchField>
     */
    private function loadFields(
        ReflectionClass $reflection,
        Searchable $searchable
    ): array {
        $fields = [];

        // Named on the class for the property that cannot speak for itself:
        // one brought by a trait of a package that must not depend on this one.
        foreach ($searchable->fields as $name => $field) {
            if (! in_array($name, $searchable->except, true)) {
                $field->name = $name;
                $fields[] = $field;
            }
        }

        foreach ($reflection->getProperties() as $property) {
            if (in_array($property->getName(), $searchable->except, true)
                || [] !== $property->getAttributes(SearchIgnore::class)) {
                continue;
            }

            // IS_INSTANCEOF, because what is written on the property is a kind —
            // `#[SearchText]` — and never the abstract it extends.
            foreach ($property->getAttributes(AbstractSearchField::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $field = $attribute->newInstance();
                $field->name = $property->getName();
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
