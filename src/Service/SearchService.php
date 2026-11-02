<?php

namespace Wexample\SymfonySearch\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Entity\SearchResult;
use Wexample\SymfonySearch\Interface\SearchProviderInterface;

/**
 * The one door in: a query goes to every provider that wants it, and what they
 * answer comes back sorted and cut.
 *
 * The cut happens twice on purpose. Once per provider, so that one family of
 * results holding a thousand matches cannot crowd out the four others; then
 * once on the merged list, which is what the caller asked for. It is the
 * legacy's behaviour, and the reason a header search shows a bit of everything
 * rather than ten invoices.
 */
class SearchService
{
    /**
     * @param iterable<SearchProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SearchProviderInterface::TAG)]
        private readonly iterable $providers,
        private readonly Security $security,
        private readonly string $minimumRole,
        private readonly int $maxResultsDefault,
        private readonly int $maxResultsLimit = 50,
        private readonly int $maxTermsLength = 255,
    ) {
    }

    /**
     * @return array<SearchResult> best first
     */
    public function search(SearchQuery $query): array
    {
        // Nothing typed asks nothing — unless one kind is named, which then
        // lists itself if its provider says it can (listsWhenEmpty()): the
        // records a picker shows before anything is typed.
        if (($query->isEmpty() && null === $query->type) || ! $this->isGranted()) {
            return [];
        }

        // Longer than anyone types to find a record: not a search.
        if (mb_strlen($query->terms) > $this->maxTermsLength) {
            return [];
        }

        // No number asked takes the default; a number asked never passes the
        // limit — it comes from the client, and nothing else bounds it.
        if (SearchQuery::MAX_RESULTS_UNSET === $query->maxResults) {
            $query = $query->withMaxResults($this->maxResultsDefault);
        } elseif ($query->maxResults < 0 || $query->maxResults > $this->maxResultsLimit) {
            $query = $query->withMaxResults(min(max($query->maxResults, 1), $this->maxResultsLimit));
        }

        $results = [];

        foreach ($this->providers as $provider) {
            if (! $provider->supports($query)) {
                continue;
            }

            $providerResults = [...$provider->provide($query)];

            self::sortByScore($providerResults);

            $results = [
                ...$results,
                ...array_slice($providerResults, 0, $query->maxResults),
            ];
        }

        self::sortByScore($results);

        return array_slice($results, 0, $query->maxResults);
    }

    /**
     * Whether anyone is allowed to search at all.
     *
     * `ROLE_ANONYMOUS` is nobody's role in Symfony — it is the way this bundle
     * spells "do not ask", for the application that has something public to
     * offer.
     */
    public function isGranted(): bool
    {
        return RoleHelper::ROLE_ANONYMOUS === $this->minimumRole
            || $this->security->isGranted($this->minimumRole);
    }

    /**
     * @param array<SearchResult> $results
     */
    private static function sortByScore(array &$results): void
    {
        // A spaceship, where the legacy compared with `<` and returned a bool:
        // usort reads that as 0 or 1 and never as -1, so two results it judged
        // in the wrong order stayed in the order they arrived.
        usort(
            $results,
            static fn (SearchResult $a, SearchResult $b): int => $b->getScore() <=> $a->getScore()
        );
    }
}
