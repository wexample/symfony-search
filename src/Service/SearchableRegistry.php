<?php

namespace Wexample\SymfonySearch\Service;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use Wexample\SymfonySearch\Attribute\AbstractSearchField;
use Wexample\SymfonySearch\Attribute\Searchable;
use Wexample\SymfonySearch\Attribute\SearchAmount;
use Wexample\SymfonySearch\Attribute\SearchIgnore;
use Wexample\SymfonySearch\Attribute\SearchText;
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
    /**
     * What a column type says a field is, for a field named without a kind.
     *
     * Only the mappings nobody would argue with: a string is read, a decimal is
     * a figure. Anything else — a date, a boolean, an enum — has to say what it
     * is, because guessing there would be guessing what the search is for.
     */
    private const array KINDS_BY_COLUMN_TYPE = [
        Types::STRING => SearchText::class,
        Types::TEXT => SearchText::class,
        Types::DECIMAL => SearchAmount::class,
        Types::FLOAT => SearchAmount::class,
    ];

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
                $this->loadFields($metadata, $reflection, $searchable)
            );

            $entities[$entity->getType()] = $entity;
        }

        return $entities;
    }

    /**
     * The fields of one entity: those named on its attribute, then those its
     * properties declare.
     *
     * `getProperties()` reports what a trait brought as if the class had written
     * it, so a trait may carry a column and the way it is searched together —
     * but only a trait of a package allowed to name this one. The traits of
     * `wexample/symfony-helpers` are not: the api requires them and this package
     * requires the api, so the dependency would close a circle, and the suite's
     * own check refuses it. Their columns are named on the class instead.
     *
     * An entity that would rather not have what a trait declared redeclares the
     * property with `#[SearchIgnore]`, or names it in the attribute's `except`.
     *
     * @return array<AbstractSearchField>
     */
    private function loadFields(
        ClassMetadata $metadata,
        ReflectionClass $reflection,
        Searchable $searchable
    ): array {
        $fields = [];

        // Named on the class for the property that cannot speak for itself:
        // one brought by a trait of a package that must not name this one.
        // `'name'` alone takes the kind its column implies; `'body' => new
        // SearchText(points: 5)` says it, where the default is not wanted.
        foreach ($searchable->fields as $key => $declaration) {
            $name = is_int($key) ? $declaration : $key;

            if (in_array($name, $searchable->except, true)) {
                continue;
            }

            $field = $declaration instanceof AbstractSearchField
                ? $declaration
                : $this->inferField($metadata, $name);

            $field->name = $name;
            $fields[] = $field;
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

    /** The kind a named field takes when it did not say one. */
    private function inferField(
        ClassMetadata $metadata,
        string $name
    ): AbstractSearchField {
        $type = $metadata->hasField($name) ? $metadata->getTypeOfField($name) : null;
        $kind = self::KINDS_BY_COLUMN_TYPE[$type] ?? null;

        if (null === $kind) {
            throw new InvalidArgumentException(
                sprintf(
                    'Searchable field "%s" of "%s" holds %s, which says nothing about how it is searched: name its kind, as in `new SearchText()`.',
                    $name,
                    $metadata->getName(),
                    null === $type ? 'no mapped column' : 'a '.$type.' column'
                )
            );
        }

        return new $kind();
    }
}
