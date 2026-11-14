<?php

declare(strict_types=1);

namespace Wexample\SymfonySearch\Tests\Unit\Class;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Class\SearchScore;
use Wexample\SymfonySearch\Enum\SearchContext;

class SearchScoreTest extends TestCase
{
    private function score(
        string $terms,
        SearchContext $context = SearchContext::DEFAULT
    ): SearchScore {
        return new SearchScore(new SearchQuery($terms, $context));
    }

    public function testANumberIsNotAWordLookedFor(): void
    {
        $this->assertSame(0.0, $this->score('15428')->text('15428')->getPoints());
        $this->assertEqualsWithDelta(40, $this->score('Dupont')->text('Dupont')->getPoints(), 0.0001);
    }

    public function testAnExactAmountComesFirst(): void
    {
        $this->assertEqualsWithDelta(SearchScore::POINTS_AMOUNT, $this->score('15428')->amount(15428)->getPoints(), 0.0001);
        // A figure starting the amount: scored as text, at a quarter.
        $this->assertEqualsWithDelta(15, $this->score('154')->amount('15428')->getPoints(), 0.0001);
        $this->assertSame(0.0, $this->score('dupont')->amount(15428)->getPoints());
        $this->assertSame(0.0, $this->score('154')->amount(null)->getPoints());
    }

    public function testEmailDateAndId(): void
    {
        $this->assertSame(0.0, $this->score('dupont')->email('dupont@example.com')->getPoints());
        $this->assertGreaterThan(0, $this->score('dupont@example.com')->email('dupont@example.com')->getPoints());

        $day = new DateTimeImmutable('2026-10-30 14:00');
        $this->assertSame(SearchScore::POINTS_DATE, $this->score('30/10/2026')->date($day)->getPoints());
        $this->assertSame(0.0, $this->score('31/10/2026')->date($day)->getPoints());

        $this->assertSame(SearchScore::POINTS_ID, $this->score('42')->id(42)->getPoints());
        $this->assertSame(0.0, $this->score('42')->id(421)->getPoints());
    }

    public function testFieldsAddUpAndTheContextFactorAppliesToTheTotal(): void
    {
        $points = $this->score('Dupont', SearchContext::HEADER)
            ->inContext(SearchContext::HEADER, .5)
            ->text('Dupont')
            ->reference('Dupont')
            ->inContext(SearchContext::FORM_FIELD, 10)
            ->getPoints();

        // (40 + 120) / 2, the factor of another context ignored.
        $this->assertEqualsWithDelta(80, $points, 0.0001);
    }
}
