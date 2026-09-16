<?php

namespace Wexample\SymfonySearch\Class\Provider;

use BackedEnum;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Interface\SearchProviderInterface;

/**
 * What every provider does the same way, which is almost everything but the
 * looking itself.
 */
abstract class AbstractSearchProvider implements SearchProviderInterface
{
    public const CLASS_NAME_SUFFIX = 'SearchProvider';

    /**
     * Read off the class name — `RouteSearchProvider` answers with `route` —
     * so that the key, the class and the `type` a client asks for are never
     * three things to keep in agreement.
     */
    public function getKey(): string
    {
        return TextHelper::toSnake(
            TextHelper::removeSuffix(
                ClassHelper::getShortName(static::class),
                self::CLASS_NAME_SUFFIX
            )
        );
    }

    /**
     * The contexts this provider answers in. Null, the default, means every
     * one of them: a provider that has nothing to restrict says nothing.
     *
     * @return array<BackedEnum|string>|null
     */
    protected function getContexts(): ?array
    {
        return null;
    }

    public function supports(SearchQuery $query): bool
    {
        if ($query->isEmpty() || ! $query->acceptsType($this->getKey())) {
            return false;
        }

        $contexts = $this->getContexts();

        return null === $contexts || $query->isInContext(...$contexts);
    }

    /**
     * A result already carrying the one thing a provider cannot forget, which
     * is saying what it is.
     */
    protected function createResult(
        string $reference,
        string $title,
    ): SearchResult {
        return new SearchResult(
            $this->getKey(),
            $reference,
            $title
        );
    }
}
