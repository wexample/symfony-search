<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;
use BackedEnum;

/**
 * Declares an entity findable.
 *
 * What it can be found *on* is said by the properties themselves, each with the
 * attribute naming its kind — `#[SearchText]`, `#[SearchAmount(points: 40)]`.
 * What is said here is what is true of the whole record: where it may be found,
 * how it is titled, where it links, and whether its points or its visibility
 * need a class rather than a declaration.
 *
 * It replaces a class. The legacy wrote one `*SearchService` per entity — seven
 * of them in network — of which six said nothing but a field list and the same
 * scoring lines around it.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Searchable
{
    /** @var array<string> */
    public readonly array $contexts;

    /**
     * @param array<BackedEnum|string>             $contexts Where this entity may be found; empty means anywhere.
     * @param array<string, AbstractSearchField>   $fields   Fields for properties that cannot carry the attribute themselves, by property name — one brought by a trait of a package that must not depend on this one. A property able to carry it says it itself.
     * @param array<string>                        $except   Properties whose search attribute is refused here — the other way of taking back what a trait declared, for the entity that would rather not redeclare the property.
     */
    public function __construct(
        array $contexts = [],
        public readonly array $fields = [],
        public readonly array $except = [],
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
         * A class implementing `SearchScoringInterface`, for the entity whose
         * points are not a list of fields — a context that halves them, a
         * relation to read, a figure worth more than a word. The properties
         * still say what the SQL looks in; this says what it is worth.
         *
         * @var class-string|null
         */
        public readonly ?string $scoring = null,
        /**
         * True when a provider written by hand serves this entity. The generic
         * provider then leaves it alone — without which both would answer and
         * every result would appear twice.
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
}
