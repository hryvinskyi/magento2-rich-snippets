<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the expression builder writes is read into a tree and printed back byte for byte, in the builder's key order
 * and encoding; what it could not have written is refused with the reason
 *
 * The vectors are the builder's own round-trip vectors, written with the module's condition fields.
 */
class ConditionTreeRoundTripTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function builderVectors(): array
    {
        return [
            'an any-root of all-groups beside a bare condition' => [self::json(self::combine('any', [
                self::combine('all', [
                    self::leaf('product_type', '==', 'simple'),
                    self::leaf('attribute_set', '==', '4'),
                ]),
                self::combine('all', [self::leaf('weight', '>=', '3')]),
                self::leaf('product_type', '==', 'virtual'),
            ]))],
            'a nested any-group under an all-root' => [self::json(self::combine('all', [
                self::leaf('product_type', '==', 'simple'),
                self::combine('any', [self::leaf('attribute_set', '==', '4'), self::leaf('weight', '<', '2')]),
            ]))],
            'a single-child group kept as written' => [self::json(self::combine('any', [
                self::combine('all', [self::leaf('product_type', '==', 'simple')]),
            ]))],
            'a condition stored with is_value_processed false' => [self::json(self::combine('all', [
                ['type' => 'product_type', 'attribute' => null, 'operator' => '==', 'value' => 'simple',
                    'is_value_processed' => false],
                self::leaf('attribute_set', '==', '4'),
            ]))],
            'A and (B or C) and D' => [self::json(self::combine('all', [
                self::leaf('product_type', '==', 'simple'),
                self::combine('any', [self::leaf('attribute_set', '==', '4'), self::leaf('attribute_set', '==', '9')]),
                self::leaf('weight', '<=', '20'),
            ]))],
            'a list value, a slash and letters beyond ASCII written as they are' => [self::json(self::combine('all', [
                self::leaf('attribute_set', '()', '4,9'),
                self::leaf('cms_page', '!=', 'über/uns'),
            ]))],
            'a condition still without a value' => [self::json(self::combine('all', [
                self::leaf('product_type', '==', ''),
                self::leaf('attribute_set', '!()', '9'),
            ]))],
            'groups nested five deep' => [self::json(self::combine('all', [self::combine('any', [
                self::combine('all', [self::combine('any', [self::combine('all', [
                    self::leaf('product_type', '==', 'simple'),
                ])])]),
            ])]))],
        ];
    }

    #[DataProvider('builderVectors')]
    public function testWhatTheBuilderWritesIsPrintedBackByteForByte(string $json): void
    {
        $printer = PlacementFixture::printer();
        $tree = PlacementFixture::parser()->parse($json);

        self::assertSame($json, $printer->print($tree));
        self::assertSame($json, $printer->print(PlacementFixture::parser()->parse($printer->print($tree))));
    }

    public function testNoConditionsReadAsAnEmptyTreeAndPrintAsNothing(): void
    {
        $empty = PlacementFixture::parser()->parse('');

        self::assertTrue($empty->isEmpty());
        self::assertSame('', PlacementFixture::printer()->print($empty));
        self::assertSame('', PlacementFixture::printer()->print(
            PlacementFixture::parser()->parse(self::json(self::combine('any', [])))
        ));
    }

    public function testEmptyGroupsAreDroppedWhenPrinted(): void
    {
        $simple = self::leaf('product_type', '==', 'simple');
        $tree = PlacementFixture::parser()->parse(self::json(self::combine('all', [
            $simple,
            self::combine('any', []),
            self::combine('any', [self::combine('all', [])]),
        ])));

        self::assertSame(self::json(self::combine('all', [$simple])), PlacementFixture::printer()->print($tree));
    }

    public function testTheTreeKeepsItsGroupsAggregatorsAndConditions(): void
    {
        $tree = PlacementFixture::parser()->parse(self::json(self::combine('any', [
            self::leaf('product_type', '==', ''),
            self::combine('all', [self::leaf('attribute_set', '()', '4,9'), self::leaf('weight', '>', '1.5')]),
        ])));

        self::assertSame(ConditionAggregator::Any, $tree->getAggregator());
        self::assertTrue($tree->hasNestedGroup());
        self::assertCount(3, $tree->getLeaves());
        self::assertCount(2, $tree->getCompleteLeaves());
        self::assertSame(
            [['product_type', '==', ''], ['attribute_set', '()', '4,9'], ['weight', '>', '1.5']],
            array_map(
                static fn ($leaf): array => [$leaf->getField(), $leaf->getOperator(), $leaf->getValue()],
                $tree->getLeaves()
            )
        );
    }

    public function testAnArrayValueIsReadAsTheListItStands(): void
    {
        $tree = PlacementFixture::parser()->parseDecoded(self::combine('all', [
            ['type' => 'attribute_set', 'attribute' => null, 'operator' => '()', 'value' => ['4', 9],
                'is_value_processed' => null],
        ]));

        self::assertSame('4,9', $tree->getLeaves()[0]->getValue());
    }

    public function testAStoredTreeIsRestoredWithWhatTheShopNoLongerHasAndThatNeverHolds(): void
    {
        $json = self::json(self::combine('any', [
            self::leaf('colour', '==', 'red'),
            ['type' => 'product_type', 'attribute' => 'gone', 'operator' => '==', 'value' => 'simple',
                'is_value_processed' => null],
            self::leaf('product_type', 'like', 'simple'),
        ]));

        $tree = PlacementFixture::parser()->restore($json);

        self::assertSame($json, PlacementFixture::printer()->print($tree));
        self::assertCount(3, $tree->getCompleteLeaves());
        self::assertFalse(PlacementFixture::conditions()->holds($tree, new PageEntity('product', 1, 1)));
        self::assertSame(
            '((1 = 0) OR (1 = 0) OR (1 = 0))',
            PlacementFixture::conditions()->predicate($tree, 'product', 'p.id', 1)
        );
        $this->expectException(SnippetRuleException::class);
        PlacementFixture::parser()->parse($json);
    }

    public function testRestoringRefusesOnlyATextThatIsNoTree(): void
    {
        self::assertTrue(PlacementFixture::parser()->restore('')->isEmpty());
        $this->expectException(SnippetRuleException::class);
        PlacementFixture::parser()->restore('[{"field": "product_type", "op": "is", "value": "simple"}]');
    }

    /**
     * @return array<string, array{string, SnippetRuleReason}>
     */
    public static function refusedTrees(): array
    {
        return [
            'text that is not JSON' => ['{"type":', SnippetRuleReason::ConditionInvalid],
            'a root that is not a group' => [
                self::json(self::leaf('product_type', '==', 'simple')),
                SnippetRuleReason::ConditionInvalid,
            ],
            'an unknown field' => [
                self::json(self::combine('all', [self::leaf('colour', '==', 'red')])),
                SnippetRuleReason::ConditionFieldUnknown,
            ],
            'an attribute on a field that reads none' => [
                self::json(self::combine('all', [
                    ['type' => 'product_type', 'attribute' => 'color', 'operator' => '==', 'value' => 'simple',
                        'is_value_processed' => null],
                ])),
                SnippetRuleReason::ConditionAttributeUnknown,
            ],
            'an operator the field does not offer' => [
                self::json(self::combine('all', [self::leaf('product_type', '>=', 'simple')])),
                SnippetRuleReason::ConditionOperatorUnknown,
            ],
            'an operator no one knows' => [
                self::json(self::combine('all', [self::leaf('product_type', 'like', 'simple')])),
                SnippetRuleReason::ConditionOperatorUnknown,
            ],
            'groups nested six deep' => [
                self::json(self::combine('all', [self::combine('any', [self::combine('all', [
                    self::combine('any', [self::combine('all', [self::combine('any', [
                        self::leaf('product_type', '==', 'simple'),
                    ])])]),
                ])])])),
                SnippetRuleReason::ConditionsTooDeep,
            ],
            'conditions that are no list' => [
                self::json(
                    ['conditions' => ['a' => self::leaf('product_type', '==', 'simple')]] + self::combine('all', [])
                ),
                SnippetRuleReason::ConditionInvalid,
            ],
        ];
    }

    #[DataProvider('refusedTrees')]
    public function testATreeTheBuilderCouldNotHaveWrittenIsRefused(string $json, SnippetRuleReason $reason): void
    {
        try {
            PlacementFixture::parser()->parse($json);
            self::fail('The tree was read.');
        } catch (SnippetRuleException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    /**
     * A group in the builder's key order
     *
     * @param string $aggregator
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private static function combine(string $aggregator, array $conditions): array
    {
        return [
            'type' => 'rich_snippets/combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => $aggregator,
            'conditions' => $conditions,
        ];
    }

    /**
     * A condition in the builder's key order
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     * @return array<string, mixed>
     */
    private static function leaf(string $field, string $operator, string $value): array
    {
        return ['type' => $field, 'attribute' => null, 'operator' => $operator, 'value' => $value,
            'is_value_processed' => null];
    }

    /**
     * JSON as the browser writes it
     *
     * @param array<string, mixed> $tree
     * @return string
     */
    private static function json(array $tree): string
    {
        return json_encode($tree, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
