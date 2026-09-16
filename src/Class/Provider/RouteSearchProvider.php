<?php

namespace Wexample\SymfonySearch\Class\Provider;

use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Helper\SearchScoreHelper;

/**
 * The pages of the application, which are its routes.
 *
 * The router already holds the list — it is what `debug:router` prints — so
 * nothing is declared, indexed or kept in sync here. What has to be decided is
 * which of those routes is a page someone could be looking for, and the answer
 * is made of four refusals: the internals, the ones that are not a GET, the
 * ones that cannot be linked to without data, and the ones this user may not
 * open.
 */
class RouteSearchProvider extends AbstractSearchProvider
{
    /** Set to false on a route that is a page but has no business being found. */
    public const string OPTION_SEARCHABLE = 'searchable';

    /** What to call the page, for as long as nothing translates route names. */
    public const string OPTION_TITLE = 'search_title';

    /**
     * A route named after the index of its section says nothing by repeating
     * it: `account_index` is the account page.
     */
    private const string ROUTE_NAME_SUFFIX_INDEX = '_index';

    private const string PATH_PREFIX_API = '/api/';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly Security $security,
    ) {
    }

    public function provide(SearchQuery $query): iterable
    {
        foreach ($this->router->getRouteCollection() as $name => $route) {
            if (! $this->isSearchable($name, $route)) {
                continue;
            }

            $title = $this->buildTitle($name, $route);

            $score = SearchScoreHelper::fieldsScore(
                $query->terms,
                [$title, $route->getPath()]
            );

            if ($score <= 0) {
                continue;
            }

            yield $this
                ->createResult($name, $title)
                ->setScore($score)
                ->setSubtitle($route->getPath())
                ->setUrl($this->router->generate($name));
        }
    }

    private function isSearchable(
        string $name,
        Route $route
    ): bool {
        // Symfony's own routes and this suite's `_system` ones share the
        // underscore, and neither is a page.
        if (str_starts_with($name, '_')) {
            return false;
        }

        if (false === $route->getOption(self::OPTION_SEARCHABLE)) {
            return false;
        }

        if (str_starts_with($route->getPath(), self::PATH_PREFIX_API)) {
            return false;
        }

        $methods = $route->getMethods();

        if ([] !== $methods && ! in_array(Request::METHOD_GET, $methods, true)) {
            return false;
        }

        if ($this->hasRequiredParameters($route)) {
            return false;
        }

        return $this->isGranted($route);
    }

    /**
     * Whether the route needs something the search does not have. A result has
     * to carry a url, and a url for `/invoice/{id}` cannot be built out of a
     * list of pages — that page is found as an invoice, not as a page.
     */
    private function hasRequiredParameters(Route $route): bool
    {
        $defaults = $route->getDefaults();

        foreach ($route->compile()->getPathVariables() as $variable) {
            if (! array_key_exists($variable, $defaults)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What `#[IsGranted]` on the action says, asked of the current user.
     *
     * A route protected by anything else — a firewall pattern, an access
     * control rule, a check inside the action — is not seen from here and is
     * listed. Declaring the attribute is what makes a page hideable.
     */
    private function isGranted(Route $route): bool
    {
        $method = self::resolveController($route);

        if (! $method instanceof ReflectionMethod) {
            return true;
        }

        $attributes = [
            ...$method->getAttributes(IsGranted::class),
            ...$method->getDeclaringClass()->getAttributes(IsGranted::class),
        ];

        foreach ($attributes as $attribute) {
            /** @var IsGranted $isGranted */
            $isGranted = $attribute->newInstance();

            if (! $this->security->isGranted($isGranted->attribute)) {
                return false;
            }
        }

        return true;
    }

    private static function resolveController(Route $route): ?ReflectionMethod
    {
        $controller = $route->getDefault('_controller');

        if (! is_string($controller) || ! str_contains($controller, '::')) {
            return null;
        }

        [$class, $method] = explode('::', $controller, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        return new ReflectionMethod($class, $method);
    }

    private function buildTitle(
        string $name,
        Route $route
    ): string {
        $title = $route->getOption(self::OPTION_TITLE);

        if (is_string($title)) {
            return $title;
        }

        return ucfirst(
            str_replace(
                '_',
                ' ',
                TextHelper::removeSuffix($name, self::ROUTE_NAME_SUFFIX_INDEX)
            )
        );
    }
}
