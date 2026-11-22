<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shop's parser reads what the builder writes with the shop's fields, product attributes and operators, prints
 * it back byte for byte, and refuses a tree the builder could not have written, the shared tree validator included
 */
class ConditionTreeParserTest extends TestCase
{
    public function testProductAttributeConditionsArePrintedBackByteForByte(): void
    {
        $json = $this->json($this->group('all', [
            $this->leaf('product_attribute', '>=', '1.5', 'weight'),
            $this->group('any', [
                $this->leaf('product_attribute', '{}', 'Bag', 'name'),
                $this->leaf('product_attribute', '!=', 'RS-1', 'sku'),
                $this->leaf('product_type', '()', 'simple,virtual'),
            ]),
            $this->leaf('product_attribute', '==', '', 'name'),
        ]));

        $tree = $this->parser()->parse($json);

        self::assertSame($json, $this->printer()->print($tree));
        self::assertSame(['weight', 'name', 'sku', null, 'name'], array_map(
            static fn ($leaf): ?string => $leaf->getAttribute(),
            $tree->getLeaves()
        ));
        self::assertCount(4, $tree->getCompleteLeaves());
    }

    /**
     * @return array<string, array{array<string, mixed>, SnippetRuleReason}>
     */
    public static function refusedTrees(): array
    {
        $deep = self::leafOf('product_type', '==', 'simple');
        foreach (['any', 'all', 'any', 'all', 'any'] as $aggregator) {
            $deep = self::groupOf($aggregator, [$deep]);
        }

        return [
            'a type the shop has no field for' => [
                self::groupOf('all', [self::leafOf('evil', '==', 'x')]),
                SnippetRuleReason::ConditionFieldUnknown,
            ],
            'groups nested deeper than the builder lets an admin nest them' => [
                self::groupOf('all', [$deep]),
                SnippetRuleReason::ConditionsTooDeep,
            ],
            'an attribute no condition may read' => [
                self::groupOf('all', [self::leafOf('product_attribute', '==', '5', 'tier_price')]),
                SnippetRuleReason::ConditionAttributeUnknown,
            ],
            'an operator the attribute does not offer' => [
                self::groupOf('all', [self::leafOf('product_attribute', '{}', '5', 'weight')]),
                SnippetRuleReason::ConditionOperatorUnknown,
            ],
            'a condition carrying conditions of a type no field has' => [
                self::groupOf('all', [
                    self::leafOf('product_type', '==', 'simple') + [
                        'conditions' => [self::leafOf('evil', '==', 'x')],
                    ],
                ]),
                SnippetRuleReason::ConditionInvalid,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $tree
     * @param SnippetRuleReason $reason
     */
    #[DataProvider('refusedTrees')]
    public function testATreeTheBuilderCouldNotHaveWrittenIsRefused(array $tree, SnippetRuleReason $reason): void
    {
        try {
            $this->parser()->parse($this->json($tree));
            self::fail('The tree was read.');
        } catch (SnippetRuleException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    /**
     * @param string $aggregator
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private function group(string $aggregator, array $conditions): array
    {
        return self::groupOf($aggregator, $conditions);
    }

    /**
     * @param string $field
     * @param string $operator
     * @param string $value
     * @param string|null $attribute
     * @return array<string, mixed>
     */
    private function leaf(string $field, string $operator, string $value, ?string $attribute = null): array
    {
        return self::leafOf($field, $operator, $value, $attribute);
    }

    /**
     * A group in the builder's key order
     *
     * @param string $aggregator
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private static function groupOf(string $aggregator, array $conditions): array
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
     * @param string|null $attribute
     * @return array<string, mixed>
     */
    private static function leafOf(string $field, string $operator, string $value, ?string $attribute = null): array
    {
        return [
            'type' => $field,
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
            'is_value_processed' => null,
        ];
    }

    /**
     * JSON as the browser writes it
     *
     * @param array<string, mixed> $tree
     * @return string
     */
    private function json(array $tree): string
    {
        return json_encode($tree, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return ConditionTreeParserInterface
     */
    private function parser(): ConditionTreeParserInterface
    {
        $parser = Bootstrap::getObjectManager()->get(ConditionTreeParserInterface::class);
        self::assertInstanceOf(ConditionTreeParserInterface::class, $parser);

        return $parser;
    }

    /**
     * @return ConditionTreePrinterInterface
     */
    private function printer(): ConditionTreePrinterInterface
    {
        $printer = Bootstrap::getObjectManager()->get(ConditionTreePrinterInterface::class);
        self::assertInstanceOf(ConditionTreePrinterInterface::class, $printer);

        return $printer;
    }
}
