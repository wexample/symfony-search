<?php

namespace Wexample\SymfonySearch\Entity;

use Symfony\Component\Uid\Uuid;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * One thing found, whatever kind of thing it is.
 *
 * An entity without an ORM mapping. Nothing is ever persisted from here — the
 * row it points at already exists, and a page points at no row at all. It is an
 * entity for what being one gives on the way out: the pseudocode export, the
 * generated `SearchResult.ts` and its repository, and a collection of results
 * being a collection of entities like any other, which the front already knows
 * how to render. Doctrine skips a class carrying no `#[ORM\Entity]`, so
 * extending `AbstractEntity` costs nothing but the identity it brings.
 *
 * That is also why the `Has*Trait` of symfony-helpers are not used here: they
 * carry `#[Column]`, and a column is exactly what none of these fields has.
 */
#[PseudocodeExport(inherited: true)]
class SearchResult extends AbstractEntity
{
    /**
     * Fixed namespace the identity is hashed under, so the same thing found
     * twice is the same result twice — in another request, or in another
     * process answering the same query.
     */
    public const ID_NAMESPACE = '9a487ec6-9094-485e-ab7b-5fadfdf25c8f';

    /**
     * What kind of thing was found: an entity path such as `invoice`, or the
     * key of whatever provider answers with something that is not an entity.
     */
    protected string $type;

    /**
     * What identifies it inside that kind — an entity id, a route name. Opaque
     * here on purpose: only the provider that emitted it knows how to read it
     * back.
     */
    protected string $reference;

    protected string $title;

    protected ?string $subtitle = null;

    /** Where clicking the result goes, already generated. */
    protected ?string $url = null;

    protected ?string $icon = null;

    /**
     * How well it answers, filled after the query rather than by it — see the
     * providers. Zero until someone scores it.
     */
    protected float $score = 0;

    /**
     * Anything the front needs and no other field holds. Kept free rather than
     * grown into columns: a result is read once and thrown away.
     */
    protected array $data = [];

    public function __construct(
        string $type,
        string $reference,
        string $title,
    ) {
        parent::__construct();

        $this->type = $type;
        $this->reference = $reference;
        $this->title = $title;

        $this->setId(self::idFor($type, $reference));
    }

    /** The identity of one found thing, which is its kind and its reference. */
    public static function idFor(
        string $type,
        string $reference,
    ): Uuid {
        return Uuid::v5(
            Uuid::fromString(self::ID_NAMESPACE),
            $type."\0".$reference
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
