<?php

declare(strict_types=1);

namespace Wexample\SymfonySearch\Tests\Unit\Class;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonySearch\Class\SearchQuery;
use Wexample\SymfonySearch\Enum\SearchContext;

class SearchQueryTest extends TestCase
{
    public function testTermsAreTrimmedAndTheContextHeldAsAString(): void
    {
        $query = new SearchQuery('  dupont ', SearchContext::HEADER);

        $this->assertSame('dupont', $query->terms);
        $this->assertSame(SearchContext::HEADER->value, $query->context);
        $this->assertTrue($query->isInContext('nope', SearchContext::HEADER));
        $this->assertFalse($query->isInContext(SearchContext::FORM_FIELD));
        $this->assertTrue((new SearchQuery('   '))->isEmpty());
    }

    public function testShapes(): void
    {
        $this->assertTrue((new SearchQuery('-12.5'))->isNumeric());
        $this->assertFalse((new SearchQuery('12 rue'))->isNumeric());
        $this->assertTrue((new SearchQuery('a@b.fr'))->isEmail());
        $this->assertFalse((new SearchQuery('a@'))->isEmail());
    }

    public function testAmount(): void
    {
        $this->assertSame(1250.5, (new SearchQuery('1 250,50 €'))->getAmount());
        $this->assertNull((new SearchQuery('dupont'))->getAmount());
    }

    public function testDate(): void
    {
        $this->assertSame('2026-10-30', (new SearchQuery('2026-10-30'))->getDate()?->format('Y-m-d'));
        $this->assertSame('2026-10-30', (new SearchQuery('30/10/2026'))->getDate()?->format('Y-m-d'));
        $this->assertSame('2026-10-30', (new SearchQuery('30.10.2026'))->getDate()?->format('Y-m-d'));
        $this->assertSame(date('Y').'-10-30', (new SearchQuery('30/10'))->getDate()?->format('Y-m-d'));
        // Read as written: no rolling over into March.
        $this->assertNull((new SearchQuery('31/02/2026'))->getDate());
        $this->assertNull((new SearchQuery('dupont'))->getDate());
    }

    public function testTypesAndFilters(): void
    {
        $query = new SearchQuery('x', type: 'project, ,scenario', filters: ['scenario' => 'abc', 'empty' => '']);

        $this->assertSame(['project', 'scenario'], $query->getTypes());
        $this->assertTrue($query->acceptsType('scenario'));
        $this->assertFalse($query->acceptsType('invoice'));
        $this->assertSame('abc', $query->getFilter('scenario'));
        $this->assertNull($query->getFilter('empty'));
        $this->assertNull($query->getFilter('missing'));

        $everyone = new SearchQuery('x');
        $this->assertSame([], $everyone->getTypes());
        $this->assertTrue($everyone->acceptsType('invoice'));
    }

    public function testWithMaxResultsKeepsTheRestOfTheQuestion(): void
    {
        $query = (new SearchQuery('x', SearchContext::FORM_FIELD, type: 'invoice', filters: ['a' => 'b']))->withMaxResults(5);

        $this->assertSame(5, $query->maxResults);
        $this->assertSame('x', $query->terms);
        $this->assertSame(SearchContext::FORM_FIELD->value, $query->context);
        $this->assertSame('invoice', $query->type);
        $this->assertSame(['a' => 'b'], $query->filters);
    }
}
