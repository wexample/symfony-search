<?php

namespace Wexample\SymfonySearch\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonySearch\Entity\SearchResult;

trait SearchResultManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return SearchResult::class;
    }
}
