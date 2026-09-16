<?php

namespace Wexample\SymfonySearch\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonySearch\Attribute\Searchable;
use Wexample\SymfonySearch\Class\SearchableEntity;

/**
 * Every entity that declared itself findable.
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
            $attributes = $metadata
                ->getReflectionClass()
                ->getAttributes(Searchable::class);

            if ([] === $attributes) {
                continue;
            }

            // Repeatable, but only the first is read: a second one means a
            // second way of searching the same entity, and that is a provider
            // written by hand rather than a line in an attribute.
            $entity = new SearchableEntity(
                $metadata->getName(),
                $attributes[0]->newInstance()
            );

            $entities[$entity->getType()] = $entity;
        }

        return $entities;
    }
}
