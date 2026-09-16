<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;
use BackedEnum;

/**
 * Declares an entity findable, and says on what.
 *
 * It replaces a class. The legacy wrote one `*SearchService` per entity — seven
 * of them in network — of which most said nothing but a field list and a score
 * made of those same fields. What is left to write by hand is the case that
 * actually reasons: a provider of its own, for the entity whose visibility
 * depends on who is asking.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Searchable
{
    /** @var array<string> */
    public readonly array $contexts;

    /**
     * @param array<string>           $fields   Properties the terms are looked for in, and scored against.
     * @param array<BackedEnum|string> $contexts Where this entity may be found; empty means anywhere.
     */
    public function __construct(
        public readonly array $fields,
        array $contexts = [],
        /**
         * How much this kind of thing matters against the others. The score of
         * every field is multiplied by it, so a house entity can outrank a log
         * line matching just as well.
         */
        public readonly float $weight = 1,
        /**
         * Which property is shown as the title. Defaults to the first searched
         * field, which is the one a user recognises a record by often enough.
         */
        public readonly ?string $titleField = null,
        public readonly ?string $subtitleField = null,
        /**
         * The route a result links to, receiving the entity id as `id`. Without
         * it a result carries no url and the front decides what to do.
         */
        public readonly ?string $route = null,
        public readonly ?string $icon = null,
        /**
         * True when a provider written by hand serves this entity. The generic
         * provider then leaves it alone — without which both would answer and
         * every result would appear twice. The field list stays here even so:
         * it is what the hand-written provider reads back, and it is declared
         * once either way.
         */
        public readonly bool $provider = false,
    ) {
        // Cases in, strings out: an application declares its contexts in its
        // own enum, and nothing downstream has to know that enum exists.
        $this->contexts = array_map(
            static fn (BackedEnum|string $context): string => $context instanceof BackedEnum
                ? (string) $context->value
                : $context,
            $contexts
        );
    }

    public function getTitleField(): string
    {
        return $this->titleField ?? $this->fields[0];
    }
}
