<?php

namespace Wexample\SymfonySearch\Traits;

use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonySearch\WexampleSymfonySearchBundle;

trait SymfonySearchBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonySearchBundle::class;
    }
}
