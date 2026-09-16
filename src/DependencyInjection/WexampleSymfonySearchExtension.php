<?php

namespace Wexample\SymfonySearch\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonySearch\Interface\SearchProviderInterface;
use Wexample\SymfonySearch\Interface\SearchScoringInterface;

class WexampleSymfonySearchExtension extends AbstractWexampleSymfonyExtension
{
    public const PARAMETER_MINIMUM_ROLE = 'wexample_symfony_search.minimum_role';

    public const PARAMETER_MAX_RESULTS = 'wexample_symfony_search.max_results';

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        // Tagging on the interface rather than through a compiler pass: a
        // provider written in the host application is autoconfigured like any
        // other service, so it joins the iterator without the application
        // knowing the tag name.
        $container
            ->registerForAutoconfiguration(SearchProviderInterface::class)
            ->addTag(SearchProviderInterface::TAG);

        // A scoring class is named on the attribute and fetched by that name:
        // the tag is what lets a locator hand it back, wired like any service.
        $container
            ->registerForAutoconfiguration(SearchScoringInterface::class)
            ->addTag(SearchScoringInterface::TAG);

        $config = $this->processConfiguration(
            new Configuration(),
            $configs
        );

        $container->setParameter(
            self::PARAMETER_MINIMUM_ROLE,
            $config['minimum_role']
        );

        $container->setParameter(
            self::PARAMETER_MAX_RESULTS,
            $config['max_results']
        );
    }
}
