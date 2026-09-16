<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;
use BackedEnum;
use Wexample\SymfonySearch\Class\Field\AbstractField;
use Wexample\SymfonySearch\Class\Field\TextField;

/**
 * Declares an entity findable, and says on what.
 *
 * It replaces a class. The legacy wrote one `*SearchService` per entity — seven
 * of them in network — of which six said nothing but a field list and the same
 * scoring lines around it. What is left to write by hand is the case that
 * actually reasons: a scoring class, for the entity whose points depend on the
 * context or on a relation; a provider, for the one whose visibility depends
 * on who is asking.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Searchable
{
    /** @var array<AbstractField> */
    public readonly array $fields;

    /** @var array<string> */
    public readonly array $contexts;

    /**
     * @param array<AbstractField|string> $fields   What the terms are looked for in, each with its kind: `new TextField('title')`, `new AmountField('priceTotal', points: 40)`. A bare string is a text field.
     * @param array<BackedEnum|string>    $contexts Where this entity may be found; empty means anywhere.
     */
    public function __construct(
        array $fields,
        array $contexts = [],
        /**
         * Which property is shown as the title. Defaults to the first field,
         * which is the one a user recognises a record by often enough.
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
         * relation to read, a figure worth more than a word. The fields above
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
        $this->fields = array_map(
            static fn (AbstractField|string $field): AbstractField => $field instanceof AbstractField
                ? $field
                : new TextField($field),
            $fields
        );

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
        return $this->titleField ?? $this->fields[0]->name;
    }
}
