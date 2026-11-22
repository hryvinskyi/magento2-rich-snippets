<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Condition\Operator;

use Hryvinskyi\RichSnippets\Model\Condition\Operator\ConditionOperatorPool;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\EqualsOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\ValueComparison;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FakeValueSql;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The operators by code: each says whether a page's values meet the condition's values, and writes the same test as
 * SQL over a value expression
 */
class ConditionOperatorPoolTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>, string, bool}>
     */
    public static function verdicts(): array
    {
        return [
            'is: the same text in another case' => ['==', ['Simple'], 'simple', true],
            'is: numbers compared as numbers' => ['==', ['12.0'], '12', true],
            'is: another value' => ['==', ['virtual'], 'simple', false],
            'is: one of several page values' => ['==', ['red', 'blue'], 'blue', true],
            'is: no page value' => ['==', [], 'simple', false],
            'is not: another value' => ['!=', ['virtual'], 'simple', true],
            'is not: no page value' => ['!=', [], 'simple', true],
            'is one of: listed' => ['()', ['9'], '4,9', true],
            'is one of: not listed' => ['()', ['5'], '4,9', false],
            'is not one of: not listed' => ['!()', ['5'], '4,9', true],
            'contains: a part in another case' => ['{}', ['Leather satchel'], 'SATCH', true],
            'contains: no such part' => ['{}', ['Leather satchel'], 'canvas', false],
            'does not contain: no such part' => ['!{}', ['Leather satchel'], 'canvas', true],
            'at least: above' => ['>=', ['12'], '10', true],
            'at least: the same' => ['>=', ['10'], '10', true],
            'greater than: the same' => ['>', ['10'], '10', false],
            'at most: below' => ['<=', ['2'], '10', true],
            'less than: numbers, not text' => ['<', ['9'], '10', true],
            'less than: no page value never holds' => ['<', [], '10', false],
            'at least: days compare as text' => ['>=', ['2026-11-20'], '2026-11-03', true],
        ];
    }

    /**
     * @param string $code
     * @param list<string> $pageValues
     * @param string $value the condition's value as stored
     * @param bool $holds
     */
    #[DataProvider('verdicts')]
    public function testAnOperatorSaysWhetherThePageValuesMeetTheCondition(
        string $code,
        array $pageValues,
        string $value,
        bool $holds
    ): void {
        $operator = PlacementFixture::operators()->get($code);

        self::assertSame($holds, $operator->holds($pageValues, $operator->values($value)));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function predicates(): array
    {
        return [
            'is' => ['==', 'simple', "EXISTS(v = 'simple')"],
            'is not' => ['!=', 'simple', "NOT (EXISTS(v = 'simple'))"],
            'is one of' => ['()', '4,9', "EXISTS(v IN ('4', '9'))"],
            'is not one of' => ['!()', '4,9', "NOT (EXISTS(v IN ('4', '9')))"],
            'contains, its wildcards escaped' => ['{}', '50%_off', "EXISTS(v LIKE '%50\\%\\_off%')"],
            'at least a number' => ['>=', '10', "EXISTS(CAST(v AS DECIMAL(30,10)) >= '10')"],
            'less than a day' => ['<', '2026-11-03', "EXISTS(v < '2026-11-03')"],
        ];
    }

    #[DataProvider('predicates')]
    public function testAnOperatorWritesItsTestAsSql(string $code, string $value, string $sql): void
    {
        $operator = PlacementFixture::operators()->get($code);

        self::assertSame($sql, $operator->predicate(new FakeValueSql('v'), $operator->values($value)));
    }

    public function testOnlyTheMembershipOperatorsTakeAListAndSplitTheirValue(): void
    {
        $pool = PlacementFixture::operators();

        self::assertTrue($pool->get('()')->takesList());
        self::assertTrue($pool->get('!()')->takesList());
        self::assertFalse($pool->get('==')->takesList());
        self::assertSame(['4', '9'], $pool->get('()')->values('4,,9'));
        self::assertSame(['4,9'], $pool->get('==')->values('4,9'));
        self::assertSame([], $pool->get('==')->values(''));
    }

    public function testAnOperatorWordsWhatAConditionAsksAndHasALabel(): void
    {
        $pool = PlacementFixture::operators();

        self::assertSame('product type Simple', $pool->get('==')->requirement('product type', 'Simple'));
        self::assertSame('weight of at least 10', $pool->get('>=')->requirement('weight', '10'));
        self::assertSame('name not containing gift', $pool->get('!{}')->requirement('name', 'gift'));
        self::assertSame('is not one of', $pool->get('!()')->getLabel());
    }

    public function testOperatorsAreFoundByCode(): void
    {
        $pool = PlacementFixture::operators();

        self::assertTrue($pool->has('!{}'));
        self::assertFalse($pool->has('like'));
        self::assertSame('<=', $pool->get('<=')->getCode());
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No condition operator has the code "like".');

        PlacementFixture::operators()->get('like');
    }

    public function testAnOperatorRegisteredUnderAnotherCodeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The condition operator "!=" is not an operator with that code.');

        new ConditionOperatorPool(['!=' => new EqualsOperator(new ValueComparison())]);
    }
}
