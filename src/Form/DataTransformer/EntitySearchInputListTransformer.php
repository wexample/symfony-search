<?php

namespace Wexample\SymfonySearch\Form\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * The records a field holds, and the identifiers it posts — one per value.
 *
 * The same check as for one record, run over a list: each identifier goes back
 * through the road the search took to offer it, so a list is not a way of
 * slipping in a record the person could not have been offered.
 *
 * @implements DataTransformerInterface<Collection<int, AbstractEntity>|array<AbstractEntity>|null, list<string>>
 */
class EntitySearchInputListTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly EntitySearchInputTransformer $record,
    ) {
    }

    /**
     * @return list<string>
     */
    public function transform(mixed $value): array
    {
        return array_values(
            array_map(
                $this->record->transform(...),
                $this->toList($value)
            )
        );
    }

    /**
     * @return Collection<int, AbstractEntity>
     */
    public function reverseTransform(mixed $value): Collection
    {
        $records = new ArrayCollection();

        foreach ($this->toList($value) as $id) {
            if (! is_scalar($id)) {
                throw new TransformationFailedException('A list of identifiers is expected here.');
            }

            $record = $this->record->reverseTransform((string) $id);

            // The same record twice is still one record: a posted list is not
            // where a relation gains a duplicate.
            if (null !== $record && ! $records->contains($record)) {
                $records->add($record);
            }
        }

        return $records;
    }

    /**
     * @return array<mixed>
     */
    private function toList(mixed $value): array
    {
        return match (true) {
            null === $value, '' === $value => [],
            $value instanceof Collection => $value->toArray(),
            is_array($value) => $value,
            // One value where a list is expected is a list of one.
            default => [$value],
        };
    }
}
