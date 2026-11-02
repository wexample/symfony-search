<?php

namespace Wexample\SymfonySearch\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

class Configuration implements ConfigurationInterface
{
    public const MAX_RESULTS_DEFAULT = 5;

    public const MAX_RESULTS_LIMIT_DEFAULT = 50;

    public const MAX_TERMS_LENGTH_DEFAULT = 255;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_search');

        $treeBuilder->getRootNode()
            ->children()
            // Who is allowed to search at all. The legacy refused anonymous
            // searches in hard code; a role says the same thing and lets a
            // public catalogue say otherwise with ROLE_ANONYMOUS.
            ->scalarNode('minimum_role')
            ->defaultValue(RoleHelper::ROLE_USER)
            ->end()
            // How many results a query returns when it asks for no number.
            ->integerNode('max_results')
            ->min(1)
            ->defaultValue(self::MAX_RESULTS_DEFAULT)
            ->end()
            // The most a query may ask for, whatever number it gives: the
            // length comes from the client, and an unbounded one reads a whole
            // table in one call.
            ->integerNode('max_results_limit')
            ->min(1)
            ->defaultValue(self::MAX_RESULTS_LIMIT_DEFAULT)
            ->end()
            // The longest text searched: nobody types more to find a record,
            // and every field compares against it.
            ->integerNode('max_terms_length')
            ->min(1)
            ->defaultValue(self::MAX_TERMS_LENGTH_DEFAULT)
            ->end()
            ->end();

        return $treeBuilder;
    }
}
