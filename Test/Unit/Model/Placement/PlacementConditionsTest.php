<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionNodeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\TreeFactoryFake;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A conditions tree holds for a page when its groups say so: "all" needs every asked child, "any" one of them; a
 * condition about another kind of page, or one without a value yet, is not asked and leaves its group as if it were
 * not there
 */
class PlacementConditionsTest extends TestCase
{
    /**
     * @return array<string, array{ConditionTreeInterface, string, int|null, bool}>
     */
    public static function placements(): array
    {
        $simple = ['product_type', '==', 'simple'];
        $notConfigurable = ['product_type', '!=', 'configurable'];
        $bags = ['attribute_set', '==', '4'];
        $faq = ['cms_page', '==', 'faq'];

        return [
            'no condition: every product page' => [PlacementFixture::none(), 'product', 2, true],
            'no condition: every other page' => [PlacementFixture::none(), 'other', null, true],
            'is: the page has the value' => [self::t([$simple]), 'product', 1, true],
            'is: the page has another value' => [self::t([$simple]), 'product', 2, false],
            'is not: the page has another value' => [self::t([$notConfigurable]), 'product', 1, true],
            'is not: the page has the value' => [self::t([$notConfigurable]), 'product', 2, false],
            'is one of: the page has one' => [self::t([['attribute_set', '()', '9,4']]), 'product', 1, true],
            'is not one of: the page has one' => [self::t([['attribute_set', '!()', '9,4']]), 'product', 1, false],
            'at least: the value is above' => [self::t([['weight', '>=', '10']]), 'product', 2, true],
            'at least: the value is the same' => [self::t([['weight', '>=', '12']]), 'product', 2, true],
            'less than: the value is above' => [self::t([['weight', '<', '10']]), 'product', 2, false],
            'is compares numbers as numbers' => [self::t([['weight', '==', '12.0']]), 'product', 2, true],
            'all: every condition holds' => [self::t([$simple, $bags]), 'product', 1, true],
            'all: one condition fails' => [self::t([$simple, ['attribute_set', '==', '9']]), 'product', 1, false],
            'any: one condition holds' => [
                self::t([['product_type', '==', 'bundle'], $bags], 'any'),
                'product',
                1,
                true,
            ],
            'any: none holds' => [self::t([$simple, $bags], 'any'), 'product', 2, false],
            'a product condition is not asked of a CMS page' => [self::t([$simple]), 'cms', 9, true],
            'only the CMS condition is asked of a CMS page' => [self::t([$simple, $faq]), 'cms', 5, true],
            'any: the CMS condition fails on another CMS page' => [self::t([$simple, $faq], 'any'), 'cms', 6, false],
            'a condition without a value is not asked' => [self::t([['product_type', '==', '']]), 'product', 2, true],
            'all: a condition without a value leaves the others' => [
                self::t([['product_type', '==', ''], $simple]),
                'product',
                2,
                false,
            ],
            'a page without entity has no value' => [self::t([$simple]), 'product', null, false],
        ];
    }

    /**
     * A tree of one group of conditions
     *
     * @param list<array{string, string, string}> $leaves
     * @param string $aggregator
     * @return ConditionTreeInterface
     */
    private static function t(array $leaves, string $aggregator = 'all'): ConditionTreeInterface
    {
        return PlacementFixture::leaves($leaves, $aggregator);
    }

    #[DataProvider('placements')]
    public function testAPageMeetsAPlacement(
        ConditionTreeInterface $conditions,
        string $kind,
        ?int $entityId,
        bool $holds
    ): void {
        $page = new PageEntity($kind, $entityId, 1);

        self::assertSame($holds, PlacementFixture::conditions()->holds($conditions, $page));
        self::assertSame(
            $holds,
            PlacementFixture::conditions()->mismatches($conditions, $page) === [],
            'A page that meets the placement has no mismatch; one that does not has one at least.'
        );
    }

    public function testNestedGroupsCombineAsTheyAreWritten(): void
    {
        // all of: product type is simple, and any of (attribute set is Gift cards, weight under 5)
        $conditions = $this->tree(ConditionAggregator::All, [
            $this->leaf('product_type', '==', 'simple'),
            $this->group(ConditionAggregator::Any, [
                $this->leaf('attribute_set', '==', '9'),
                $this->leaf('weight', '<', '5'),
            ]),
        ]);

        self::assertTrue(PlacementFixture::conditions()->holds($conditions, new PageEntity('product', 1, 1)));
        self::assertFalse(PlacementFixture::conditions()->holds($conditions, new PageEntity('product', 2, 1)));
    }

    public function testAGroupWithNothingAskedHoldsInsideAnAnyGroup(): void
    {
        // any of: the CMS page is FAQ, or all of (a condition without a value)
        $conditions = $this->tree(ConditionAggregator::Any, [
            $this->leaf('cms_page', '==', 'faq'),
            $this->group(ConditionAggregator::All, [$this->leaf('product_type', '==', '')]),
        ]);

        self::assertFalse(
            PlacementFixture::conditions()->holds($conditions, new PageEntity('cms', 6, 1)),
            'The empty group is not asked, so it cannot make "any" hold.'
        );
        self::assertTrue(PlacementFixture::conditions()->holds($conditions, new PageEntity('cms', 5, 1)));
    }

    public function testAConditionOnAFieldThatNoLongerExistsNeverHolds(): void
    {
        $vanished = $this->tree(ConditionAggregator::All, [$this->leaf('brand', '==', 'x')]);
        $either = $this->tree(ConditionAggregator::Any, [
            $this->leaf('brand', '==', 'x'),
            $this->leaf('product_type', '==', 'simple'),
        ]);

        self::assertFalse(PlacementFixture::conditions()->holds($vanished, new PageEntity('home', null, 1)));
        self::assertTrue(PlacementFixture::conditions()->holds($either, new PageEntity('product', 1, 1)));
    }

    public function testTheMismatchesAreTheAskedConditionsThatFail(): void
    {
        $mismatches = PlacementFixture::conditions()->mismatches(
            PlacementFixture::leaves([
                ['product_type', '==', 'configurable'],
                ['attribute_set', '==', '4'],
                ['cms_page', '==', 'faq'],
            ]),
            new PageEntity('product', 1, 1)
        );

        self::assertSame(
            [['product_type', 'product type Configurable', 'Product type', 'is', ['Configurable'], 'Simple']],
            $this->described($mismatches)
        );
    }

    public function testAMismatchListsEveryValueAndNamesNoValueOfAPageNamedByItself(): void
    {
        $mismatches = PlacementFixture::conditions()->mismatches(
            PlacementFixture::leaves([['cms_page', '()', 'faq,shipping']]),
            new PageEntity('cms', 7, 1)
        );

        self::assertSame(
            [[
                'cms_page',
                'the CMS page FAQ or Shipping & returns',
                'CMS page',
                'is one of',
                ['FAQ', 'Shipping & returns'],
                null,
            ]],
            $this->described($mismatches)
        );
    }

    public function testThePredicateNestsTheAskedConditionsOfTheKind(): void
    {
        $conditions = $this->tree(ConditionAggregator::All, [
            $this->leaf('product_type', '!=', 'configurable'),
            $this->leaf('cms_page', '==', 'faq'),
            $this->group(ConditionAggregator::Any, [
                $this->leaf('attribute_set', '()', '4,9'),
                $this->leaf('weight', '>=', '10'),
            ]),
        ]);

        self::assertSame(
            "((NOT (EXISTS(product_type(p.id) = 'configurable'))) AND "
            . "((EXISTS(attribute_set(p.id) IN ('4', '9'))) OR "
            . "(EXISTS(CAST(weight(p.id) AS DECIMAL(30,10)) >= '10'))))",
            PlacementFixture::conditions()->predicate($conditions, 'product', 'p.id', 1)
        );
        self::assertSame(
            "((EXISTS(cms_page(p.id) = 'faq')))",
            PlacementFixture::conditions()->predicate($conditions, 'cms', 'p.id', 1)
        );
    }

    public function testAKindWithoutAskedConditionsHasNoPredicateAndAVanishedFieldMatchesNothing(): void
    {
        self::assertNull(PlacementFixture::conditions()->predicate(
            PlacementFixture::leaves([['product_type', '==', 'simple']]),
            'cms',
            'p.id',
            1
        ));
        self::assertSame(
            '((1 = 0))',
            PlacementFixture::conditions()->predicate(
                $this->tree(ConditionAggregator::All, [$this->leaf('brand', '==', 'x')]),
                'category',
                'p.id',
                1
            )
        );
    }

    public function testTheVariablePathsAreThoseOfTheCompleteConditions(): void
    {
        self::assertSame(
            ['product.type_id', 'product.weight'],
            PlacementFixture::conditions()->variablePaths(PlacementFixture::leaves([
                ['product_type', '==', 'simple'],
                ['weight', '>', '1'],
                ['weight', '<', '20'],
                ['attribute_set', '==', ''],
            ]))
        );
    }

    /**
     * @param ConditionAggregator $aggregator
     * @param list<ConditionNodeInterface> $children
     * @return ConditionTreeInterface
     */
    private function tree(ConditionAggregator $aggregator, array $children): ConditionTreeInterface
    {
        return (new TreeFactoryFake())->tree($aggregator, $children);
    }

    /**
     * @param ConditionAggregator $aggregator
     * @param list<ConditionNodeInterface> $children
     * @return ConditionNodeInterface
     */
    private function group(ConditionAggregator $aggregator, array $children): ConditionNodeInterface
    {
        return (new TreeFactoryFake())->group($aggregator, $children);
    }

    /**
     * @param string $field
     * @param string $operator
     * @param string $value
     * @return ConditionNodeInterface
     */
    private function leaf(string $field, string $operator, string $value): ConditionNodeInterface
    {
        return (new TreeFactoryFake())->leaf($field, null, $operator, $value, null);
    }

    /**
     * Mismatches as field, requirement, field label, operator label, value labels and the page's own value
     *
     * @param list<ConditionMismatchInterface> $mismatches
     * @return list<array{string, string, string, string, list<string>, string|null}>
     */
    private function described(array $mismatches): array
    {
        return array_map(
            static fn (ConditionMismatchInterface $mismatch): array => [
                $mismatch->getCondition()->getField(),
                $mismatch->getRequirement(),
                $mismatch->getFieldLabel(),
                $mismatch->getOperatorLabel(),
                $mismatch->getValueLabels(),
                $mismatch->getPageValue(),
            ],
            $mismatches
        );
    }
}
