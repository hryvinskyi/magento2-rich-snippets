<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionField;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionFieldPool;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\ComparisonOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\ConditionOperatorPool;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\ContainsOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\EqualsOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\NegatedOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\OneOfOperator;
use Hryvinskyi\RichSnippets\Model\Condition\Operator\ValueComparison;
use Hryvinskyi\RichSnippets\Model\Condition\OperatorDescriptors;
use Hryvinskyi\RichSnippets\Model\Condition\Sentence\ConditionSentence;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeParser;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreePrinter;
use Hryvinskyi\RichSnippets\Model\Page\Kind\NoEntityKindResolver;
use Hryvinskyi\RichSnippets\Model\Page\PageKindPool;
use Hryvinskyi\RichSnippets\Model\Placement\AskedLeaves;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatch;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatchFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementConditions;
use Hryvinskyi\RichSnippets\Model\Placement\PoolPlacementVocabulary;
use Hryvinskyi\RichSnippets\Model\Placement\TreeEvaluation;
use Hryvinskyi\RichSnippets\Model\Placement\TreePredicate;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\UiFormElements\Model\ConditionTreeValidator;
use Hryvinskyi\UiFormElements\Model\OperatorSetPolicy;

/**
 * Placement conditions over a small shop: product 1 is a simple bag weighing 2, product 2 a configurable gift card
 * weighing 12; category 3 is Bags; CMS page 5 is the FAQ, page 6 Shipping & returns, page 7 Contact
 *
 * The fields are the module's own, over given values; the operators are the shop's ten.
 */
class PlacementFixture
{
    /**
     * The condition evaluation over the shop's fields
     *
     * @return PlacementConditions
     */
    public static function conditions(): PlacementConditions
    {
        $asked = self::asked();
        $mismatches = new class implements ConditionMismatchFactoryInterface {
            public function create(
                ConditionLeafInterface $condition,
                string $requirement,
                string $fieldLabel,
                string $operatorLabel,
                array $valueLabels,
                ?string $pageValue
            ): ConditionMismatchInterface {
                return new ConditionMismatch(
                    $condition,
                    $requirement,
                    $fieldLabel,
                    $operatorLabel,
                    $valueLabels,
                    $pageValue
                );
            }
        };

        return new PlacementConditions(
            new TreeEvaluation($asked),
            new TreePredicate($asked),
            $asked,
            $mismatches,
            new WordList()
        );
    }

    /**
     * The conditions a builder text holds, parsed as the shop parses them
     *
     * @param string $json
     * @return ConditionTreeInterface
     */
    public static function tree(string $json): ConditionTreeInterface
    {
        return self::parser()->parse($json);
    }

    /**
     * A tree of one group of conditions, each `[field, operator, value]`
     *
     * @param list<array{0: string, 1: string, 2: string}> $leaves
     * @param string $aggregator `all` or `any`
     * @return ConditionTreeInterface
     */
    public static function leaves(array $leaves, string $aggregator = 'all'): ConditionTreeInterface
    {
        return self::tree(self::json($leaves, $aggregator));
    }

    /**
     * The builder text of one group of conditions, each `[field, operator, value]`
     *
     * @param list<array{0: string, 1: string, 2: string}> $leaves
     * @param string $aggregator `all` or `any`
     * @return string
     */
    public static function json(array $leaves, string $aggregator = 'all'): string
    {
        if ($leaves === []) {
            return '';
        }
        $conditions = [];
        foreach ($leaves as [$field, $operator, $value]) {
            $conditions[] = [
                'type' => $field,
                'attribute' => null,
                'operator' => $operator,
                'value' => $value,
                'is_value_processed' => null,
            ];
        }

        return json_encode([
            'type' => ConditionTreeInterface::GROUP_TYPE,
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => $aggregator,
            'conditions' => $conditions,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * No conditions
     *
     * @return ConditionTreeInterface
     */
    public static function none(): ConditionTreeInterface
    {
        return (new TreeFactoryFake())->tree();
    }

    /**
     * The parser over the shop's fields
     *
     * @return ConditionTreeParser
     */
    public static function parser(): ConditionTreeParser
    {
        return new ConditionTreeParser(new ConditionTreeValidator(), self::fields(), new TreeFactoryFake());
    }

    /**
     * The printer
     *
     * @return ConditionTreePrinter
     */
    public static function printer(): ConditionTreePrinter
    {
        return new ConditionTreePrinter();
    }

    /**
     * The sentence builder over the shop's fields
     *
     * @return ConditionSentence
     */
    public static function sentence(): ConditionSentence
    {
        return new ConditionSentence(self::asked());
    }

    /**
     * The placement vocabulary over the shop's kinds of page and condition fields
     *
     * @return PoolPlacementVocabulary
     */
    public static function vocabulary(): PoolPlacementVocabulary
    {
        return new PoolPlacementVocabulary(self::kinds(), self::fields());
    }

    /**
     * The condition fields
     *
     * @return ConditionFieldPool
     */
    public static function fields(): ConditionFieldPool
    {
        $descriptors = new OperatorDescriptors(self::operators(), new OperatorSetPolicy());

        return new ConditionFieldPool(self::kinds(), [
            'attribute_set' => new ConditionField(
                new FakeValueSource('attribute_set', [1 => ['4'], 2 => ['9']], ['4' => 'Bags', '9' => 'Gift cards']),
                $descriptors,
                'product',
                'Attribute set',
                'attribute set'
            ),
            'product_type' => new ConditionField(
                new FakeValueSource(
                    'product_type',
                    [1 => ['simple'], 2 => ['configurable']],
                    ['simple' => 'Simple', 'configurable' => 'Configurable'],
                    ['product.type_id']
                ),
                $descriptors,
                'product',
                'Product type',
                'product type'
            ),
            'weight' => new ConditionField(
                new FakeValueSource('weight', [1 => ['2'], 2 => ['12']], [], ['product.weight']),
                $descriptors,
                'product',
                'Weight',
                'weight',
                'weight'
            ),
            'category_page' => new ConditionField(
                new FakeValueSource('category_page', [3 => ['3']], ['3' => 'Bags']),
                $descriptors,
                'category',
                'Category page',
                'the category page',
                'tree',
                false
            ),
            'cms_page' => new ConditionField(
                new FakeValueSource(
                    'cms_page',
                    [5 => ['faq'], 6 => ['shipping'], 7 => ['contact']],
                    ['faq' => 'FAQ', 'shipping' => 'Shipping & returns', 'contact' => 'Contact']
                ),
                $descriptors,
                'cms',
                'CMS page',
                'the CMS page',
                'select',
                false
            ),
        ]);
    }

    /**
     * The shop's ten operators
     *
     * @return ConditionOperatorPool
     */
    public static function operators(): ConditionOperatorPool
    {
        $comparison = new ValueComparison();
        $equals = new EqualsOperator($comparison);
        $oneOf = new OneOfOperator($comparison);
        $contains = new ContainsOperator($comparison);

        return new ConditionOperatorPool([
            '==' => $equals,
            '!=' => new NegatedOperator($equals, '!=', 'is not', '%1 other than %2'),
            '()' => $oneOf,
            '!()' => new NegatedOperator($oneOf, '!()', 'is not one of', '%1 other than %2'),
            '{}' => $contains,
            '!{}' => new NegatedOperator($contains, '!{}', 'does not contain', '%1 not containing %2'),
            '>=' => self::comparison('>=', 'equals or greater than', '%1 of at least %2', [0, 1]),
            '>' => self::comparison('>', 'greater than', '%1 greater than %2', [1]),
            '<=' => self::comparison('<=', 'equals or less than', '%1 of at most %2', [-1, 0]),
            '<' => self::comparison('<', 'less than', '%1 less than %2', [-1]),
        ]);
    }

    /**
     * An ordering operator whose SQL operator is its code
     *
     * @param string $code
     * @param string $label
     * @param string $requirement
     * @param list<int> $accepted
     * @return ComparisonOperator
     */
    private static function comparison(
        string $code,
        string $label,
        string $requirement,
        array $accepted
    ): ComparisonOperator {
        return new ComparisonOperator(new ValueComparison(), $code, $label, $requirement, $code, $accepted);
    }

    /**
     * The leaves' fields and operators
     *
     * @return AskedLeaves
     */
    public static function asked(): AskedLeaves
    {
        return new AskedLeaves(self::fields(), self::operators());
    }

    /**
     * The kinds of page
     *
     * @return PageKindPool
     */
    public static function kinds(): PageKindPool
    {
        return new PageKindPool([
            'home' => new NoEntityKindResolver(['cms_index_index']),
            'product' => new NoEntityKindResolver(['catalog_product_view']),
            'category' => new NoEntityKindResolver(['catalog_category_view']),
            'cms' => new NoEntityKindResolver(['cms_page_view']),
            'other' => new NoEntityKindResolver(),
        ], 'other');
    }
}
