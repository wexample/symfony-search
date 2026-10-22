<?php

namespace Wexample\SymfonySearch\Api\Dto;

use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonySearch\Entity\SearchResult;

/**
 * A result as it goes on the wire.
 *
 * Everything here is meant to be shown: the front draws a line out of it and
 * has nothing else to ask. What is deliberately absent is the record behind it
 * — a result says what it points at, and the client that wants the invoice asks
 * for the invoice.
 */
class PublicSearchResultDto extends AbstractEntityDto
{
    public string $type;

    public string $reference;

    public string $title;

    public ?string $subtitle;

    public ?string $url;

    public ?string $icon;

    public float $score;

    public array $data;

    /**
     * @param SearchResult $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);

        $dto->type = $entity->getType();
        $dto->reference = $entity->getReference();
        $dto->title = $entity->getTitle();
        $dto->subtitle = $entity->getSubtitle();
        $dto->url = $entity->getUrl();
        $dto->icon = $entity->getIcon();
        $dto->score = $entity->getScore();
        $dto->data = $entity->getData();

        return $dto;
    }
}
