<?php

namespace Wexample\SymfonySearch\Class\Provider;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyLoader\Service\PageService;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Helper\SearchScoreHelper;

/**
 * The pages of the application, which are its routes.
 *
 * The router already holds the list — it is what `debug:router` prints — so
 * nothing is declared, indexed or kept in sync here. What has to be decided is
 * which of those routes is a page someone could be looking for, and the answer
 * is: one served by a pages controller, reachable by GET without data, and open
 * to the current user. Being a pages controller is what the menu builder tests
 * too, so what is listed and what is found stay the same set.
 */
class RouteSearchProvider extends AbstractSearchProvider
{
    /** Set to false on a route that is a page but has no business being found. */
    public const string OPTION_SEARCHABLE = 'searchable';

    /** What to call the page, when its own translations do not say. */
    public const string OPTION_TITLE = 'search_title';

    /** The key every page holds its name under, in its own translation domain. */
    private const string TRANSLATION_KEY_TITLE = 'page_title';

    /**
     * A route named after the index of its section says nothing by repeating
     * it: `account_index` is the account page.
     */
    private const string ROUTE_NAME_SUFFIX_INDEX = '_index';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly PageService $pageService,
        private readonly TranslatorInterface $translator,
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
        if (false === $route->getOption(self::OPTION_SEARCHABLE)) {
            return false;
        }

        $controller = self::resolveController($route);

        // A page is what a pages controller serves. Everything else the router
        // knows — the profiler, an API, a fixture, a form endpoint — is reached
        // by something, but not by someone looking for it. Tested on the class
        // the route names, not on where the method was written: a template
        // route points at `resolveSimpleRoute`, which every pages controller
        // inherits from the same trait.
        if (null === $controller
            || ! $controller[0]->isSubclassOf(AbstractPagesController::class)) {
            return false;
        }

        $methods = $route->getMethods();

        if ([] !== $methods && ! in_array(Request::METHOD_GET, $methods, true)) {
            return false;
        }

        if ($this->hasRequiredParameters($route)) {
            return false;
        }

        return $this->isGranted(...$controller);
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
    private function isGranted(
        ReflectionClass $class,
        ReflectionMethod $method
    ): bool {
        $attributes = [
            ...$method->getAttributes(IsGranted::class),
            ...$class->getAttributes(IsGranted::class),
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

    /**
     * @return array{0: ReflectionClass, 1: ReflectionMethod}|null the class the
     *                                                             route names and the
     *                                                             action it calls
     */
    private static function resolveController(Route $route): ?array
    {
        $controller = $route->getDefault('_controller');

        if (! is_string($controller) || ! str_contains($controller, '::')) {
            return null;
        }

        [$class, $method] = explode('::', $controller, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        return [new ReflectionClass($class), new ReflectionMethod($class, $method)];
    }

    /**
     * The page's own name first — the `page_title` its translations declare,
     * which is what the menu and the browser tab show. A route option second,
     * for the page that has none. The route name humanised last, so that a
     * page is never missing from the list for lack of a label.
     */
    private function buildTitle(
        string $name,
        Route $route
    ): string {
        $translated = $this->translatePageTitle($name);

        if (null !== $translated) {
            return $translated;
        }

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

    private function translatePageTitle(string $routeName): ?string
    {
        try {
            $domain = $this->pageService->pageTranslationPathFromRoute($routeName);
        } catch (Throwable) {
            // A controller shaped unlike the loader's pages resolves to no
            // domain; that is a page without a name, not a search without
            // pages.
            return null;
        }

        $title = $this->translator->trans(self::TRANSLATION_KEY_TITLE, [], $domain);

        // Symfony answers a missing message with its id.
        return self::TRANSLATION_KEY_TITLE === $title ? null : $title;
    }
}
