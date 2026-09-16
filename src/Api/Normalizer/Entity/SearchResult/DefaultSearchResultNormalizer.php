<?php

namespace Wexample\SymfonySearch\Api\Normalizer\Entity\SearchResult;

use ArrayObject;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;
use Wexample\SymfonySearch\Api\Dto\PublicSearchResultDto;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Entity\Traits\Manipulator\SearchResultManipulatorTrait;

class DefaultSearchResultNormalizer extends AbstractEntityNormalizer
{
    use SearchResultManipulatorTrait;

    public function normalizeEntity(
        SearchResult|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return PublicSearchResultDto::fromEntity($entity);
    }
}
