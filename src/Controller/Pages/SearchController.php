<?php

namespace Wexample\SymfonySearch\Controller\Pages;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonySearch\Api\Controller\SearchResultController;
use Wexample\SymfonySearch\Attribute\AbstractSearchField;
use Wexample\SymfonySearch\Enum\SearchContext;
use Wexample\SymfonySearch\Interface\SearchProviderInterface;
use Wexample\SymfonySearch\Service\SearchableRegistry;
use Wexample\SymfonySearch\Traits\SymfonySearchBundleClassTrait;

/**
 * What this application can be searched for, read from the application itself.
 *
 * Nothing on the page is written down twice: the providers come from the tag,
 * the searchable entities from the attributes they carry, and the endpoint from
 * the router. A page that listed them by hand would be wrong the first time
 * someone added one.
 */
#[Route(path: 'search/', name: 'search_')]
final class SearchController extends AbstractPagesController
{
    use SymfonySearchBundleClassTrait;

    final public const string ROUTE_INDEX = VariableHelper::INDEX;

    #[Route(name: self::ROUTE_INDEX)]
    public function index(
        SearchableRegistry $registry,
        #[AutowireIterator(SearchProviderInterface::TAG)]
        iterable $providers,
    ): Response {
        return $this->renderPage(
            self::ROUTE_INDEX,
            [
                'providers' => $this->describeProviders($providers),
                'searchableEntities' => $this->describeEntities($registry),
                'contexts' => array_map(
                    static fn (SearchContext $context): string => $context->value,
                    SearchContext::cases()
                ),
                'apiRouteName' => 'api_search_result_'.SearchResultController::ROUTE_LIST,
            ]
        );
    }

    /**
     * @param iterable<SearchProviderInterface> $providers
     */
    private function describeProviders(iterable $providers): array
    {
        $described = [];

        foreach ($providers as $provider) {
            $described[$provider->getKey()] = $provider::class;
        }

        ksort($described);

        return $described;
    }

    private function describeEntities(SearchableRegistry $registry): array
    {
        $described = [];

        foreach ($registry->all() as $type => $searchableEntity) {
            $described[$type] = [
                'className' => $searchableEntity->className,
                'fields' => array_map(
                    static fn (AbstractSearchField $field): string => sprintf(
                        '%s (%s%s)',
                        $field->name,
                        $field->getKindName(),
                        null === $field->points ? '' : ', '.$field->points
                    ),
                    $searchableEntity->fields
                ),
                'contexts' => $searchableEntity->searchable->contexts,
                'scoring' => $searchableEntity->getScoringClass(),
                'hasOwnProvider' => $searchableEntity->hasOwnProvider(),
            ];
        }

        ksort($described);

        return $described;
    }
}
