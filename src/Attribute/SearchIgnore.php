<?php

namespace Wexample\SymfonySearch\Attribute;

use Attribute;

/**
 * Takes back what a trait declared.
 *
 * A trait bringing a column may bring the way it is searched with it, and an
 * entity using that trait without wanting that field redeclares the property
 * and marks it. Said out loud rather than left to the silence of an absent
 * attribute, because the reader of the entity has no view of the trait.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class SearchIgnore
{
}
