<?php

declare(strict_types=1);

namespace Wexample\SymfonySearch\Tests\Unit\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonySearch\Helper\SearchScoreHelper;

class SearchScoreHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, float}>
     */
    public static function scores(): iterable
    {
        yield 'nothing asked' => ['', 'Dupont', 0];
        yield 'nothing to answer' => ['dupont', null, 0];
        yield 'equal' => ['Dupont', 'Dupont', 40];
        yield 'equal but for the case' => ['dupont', 'Dupont', 20];
        yield 'a whole word at the start' => ['Jean', 'Jean Dupont', 22.5];
        yield 'a whole word elsewhere, other case' => ['dupont', 'Jean Dupont', 15];
        yield 'a fragment' => ['upon', 'Jean Dupont', 10];
        yield 'equal once the accents are dropped' => ['cafe', 'Café', 36];
        yield 'not contained' => ['martin', 'Jean Dupont', 0];
        yield 'punctuation alone' => ['%', 'a % b', 10];
        yield 'punctuation alone, nowhere' => ['%', 'Dupont', 0];
        yield 'a pattern character typed' => ['a(b', 'x a(b', 15];
    }

    #[DataProvider('scores')]
    public function testStringScore(
        ?string $needle,
        ?string $haystack,
        float $expected
    ): void {
        $this->assertEqualsWithDelta($expected, SearchScoreHelper::stringScore($needle, $haystack), 0.0001);
    }

    public function testAnAccentedCapitalMeetsItsLowerCase(): void
    {
        // Lowered byte-wise, `É` stayed `É`: the two met nowhere, not even
        // slugified (`Etat` against `etat`).
        $this->assertEqualsWithDelta(20, SearchScoreHelper::stringScore('état', 'ÉTAT'), 0.0001);
    }

    public function testAnAccentedLetterIsPartOfTheWord(): void
    {
        // A whole word after an apostrophe, its first letter accented.
        $this->assertEqualsWithDelta(15, SearchScoreHelper::stringScore('été', "l'été indien"), 0.0001);
        // The start of a longer word, which ends on an accented letter: not
        // a whole word.
        $this->assertEqualsWithDelta(15, SearchScoreHelper::stringScore('été', 'étés'), 0.0001);
    }

    public function testAnEmailAnswersOnlyAWholeAddress(): void
    {
        $this->assertSame(0.0, (float) SearchScoreHelper::emailScore('dupont', 'dupont@example.com'));
        $this->assertEqualsWithDelta(120, SearchScoreHelper::emailScore('dupont@example.com', 'dupont@example.com'), 0.0001);
    }

    public function testFieldsAddUp(): void
    {
        $this->assertEqualsWithDelta(35, SearchScoreHelper::fieldsScore('dupont', ['Dupont', null, 'Jean Dupont']), 0.0001);
    }
}
