<?php

namespace Wexample\SymfonySearch\Form\DataTransformer;

use Closure;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Class\SearchableEntity;
use Wexample\SymfonySearch\Service\EntityPicker;

/**
 * The record a field holds, and the id it posts.
 *
 * Which kind of record is known only once the field sits in its form — the
 * relation it is bound to says it — so it is asked for when needed rather than
 * given at construction.
 *
 * @implements DataTransformerInterface<AbstractEntity|string, string>
 */
class EntitySearchInputTransformer implements DataTransformerInterface
{
    /**
     * @param Closure(): SearchableEntity $target
     */
    public function __construct(
        private readonly EntityPicker $picker,
        private readonly Closure $target,
        private readonly string $context,
    ) {
    }

    public function transform(mixed $value): string
    {
        if ($value instanceof AbstractEntity) {
            return (string) $value->getId();
        }

        // A field left unmapped may be handed the id itself.
        return null === $value ? '' : (string) $value;
    }

    public function reverseTransform(mixed $value): ?AbstractEntity
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $entity = $this->picker->find(($this->target)(), (string) $value, $this->context);

        if (null === $entity) {
            // Gone since it was picked, or never one the search would have
            // offered here: either way not a value this field can carry.
            throw new TransformationFailedException(sprintf('No record "%s" can be picked here.', $value));
        }

        return $entity;
    }
}
