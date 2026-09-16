<?php

namespace Wexample\SymfonySearch\Api\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\LengthQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\SearchQueryOption;
use Wexample\SymfonyApi\Api\Attribute\QueryOption\StringQueryOption;
use Wexample\SymfonyApi\Api\Class\ApiResponse;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonySearch\Api\Normalizer\Entity\SearchResult\DefaultSearchResultNormalizer;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Enum\SearchContext;
use Wexample\SymfonySearch\Service\SearchService;

/**
 * The search, as the collection of one kind of entity.
 *
 * Nothing here is a special route: a result is an entity, so its endpoint is
 * the `list` of that entity, and the `SearchResultRepository.ts` generated from
 * the same class calls it without being told where it is.
 */
#[Route(path: 'api/search-result/', name: 'api_search_result_')]
class SearchResultController extends AbstractApiController
{
    final public const string ROUTE_LIST = 'list';

    final public const string QUERY_OPTION_CONTEXT = VariableHelper::CONTEXT;

    final public const string QUERY_OPTION_TYPE = VariableHelper::TYPE;

    #[Route(path: 'list', name: self::ROUTE_LIST, methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET, options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE)]
    #[SearchQueryOption(default: '')]
    #[StringQueryOption(key: self::QUERY_OPTION_CONTEXT, default: SearchContext::DEFAULT->value)]
    #[StringQueryOption(key: self::QUERY_OPTION_TYPE, default: '')]
    // Zero rather than a number: the bundle's configured default is what a
    // client saying nothing gets, and it is not this controller's to decide.
    #[LengthQueryOption(default: SearchQuery::MAX_RESULTS_UNSET)]
    public function list(
        Request $request,
        SearchService $searchService,
        DefaultSearchResultNormalizer $normalizer,
    ): ApiResponse {
        $type = (string) self::getQueryOptionValue($request, self::QUERY_OPTION_TYPE, '');

        $results = $searchService->search(
            new SearchQuery(
                (string) self::getQueryOptionValue($request, VariableHelper::SEARCH, ''),
                (string) self::getQueryOptionValue($request, self::QUERY_OPTION_CONTEXT, SearchContext::DEFAULT->value),
                (int) self::getQueryOptionValue($request, VariableHelper::LENGTH, SearchQuery::MAX_RESULTS_UNSET),
                '' === $type ? null : $type,
            )
        );

        return self::apiResponseCollection(
            $normalizer->normalizeCollection($results)
        );
    }
}
