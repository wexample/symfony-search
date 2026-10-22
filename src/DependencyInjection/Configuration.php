<?php

namespace Wexample\SymfonySearch\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyHelpers\Helper\RoleHelper;

class Configuration implements ConfigurationInterface
{
    public const MAX_RESULTS_DEFAULT = 5;

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
            ->end();

        return $treeBuilder;
    }
}
