<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Condition;

use Hryvinskyi\RichSnippets\Model\Condition\ConditionField;
use Hryvinskyi\RichSnippets\Model\Condition\OperatorDescriptors;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FakeValueSource;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\UiFormElements\Model\OperatorSetPolicy;
use PHPUnit\Framework\TestCase;

/**
 * A condition field of one subject reads the pages of its own kind from its value source and describes itself to
 * the expression builder
 */
class ConditionFieldTest extends TestCase
{
    public function testAPageOfTheFieldsKindHasItsEntitysValues(): void
    {
        $field = $this->field();

        self::assertSame(['simple'], $field->pageValues(new PageEntity('product', 1, 1)));
        self::assertSame(['virtual', 'gift'], $field->pageValues(new PageEntity('product', 3, 1)));
    }

    public function testAPageOfAnotherKindOrWithoutEntityHasNoValue(): void
    {
        $field = $this->field();

        self::assertSame([], $field->pageValues(new PageEntity('category', 1, 1)));
        self::assertSame([], $field->pageValues(new PageEntity('product', null, 1)));
        self::assertSame([], $field->pageValues(new PageEntity('product', 99, 1)));
    }

    public function testTheFieldIsItsOwnSubjectAndOffersNoAttribute(): void
    {
        $field = $this->field();

        self::assertSame($field, $field->subject(null));
        self::assertNull($field->subject('color'));
        self::assertNull($field->options('color', 1));
        self::assertSame([], $field->suggestions(null, 'sim'));
    }

    public function testASelectFieldOffersEqualityAndMembership(): void
    {
        self::assertSame(['==', '!=', '()', '!()'], $this->field()->getOperators());
    }

    public function testATreeFieldOffersMembershipOnly(): void
    {
        self::assertSame(['()', '!()'], $this->field('tree')->getOperators());
    }

    public function testTheBuilderEntryCarriesTheOperatorsAndTheOptions(): void
    {
        self::assertSame(
            [
                'type' => 'product_type',
                'label' => 'Product type',
                'attribute' => null,
                'valueType' => 'select',
                'operators' => [
                    ['value' => '==', 'label' => 'is'],
                    ['value' => '!=', 'label' => 'is not'],
                    ['value' => '()', 'label' => 'is one of', 'list' => true],
                    ['value' => '!()', 'label' => 'is not one of', 'list' => true],
                ],
                'options' => [
                    ['value' => 'simple', 'label' => 'Simple Product'],
                    ['value' => 'virtual', 'label' => 'Virtual Product'],
                ],
            ],
            $this->field()->builderField('product_type', 1)
        );
    }

    public function testTheWordsComeFromTheConfigurationAndTheLabelsFromTheSource(): void
    {
        $field = $this->field();

        self::assertSame('Product type', $field->getLabel());
        self::assertSame('product type', $field->getPhrase());
        self::assertSame('product', $field->getPageKind());
        self::assertSame('', $field->getUnit());
        self::assertTrue($field->namesOwnValue());
        self::assertSame('Simple Product', $field->valueLabel('simple', 1));
        self::assertNull($field->valueLabel('bundle', 1));
        self::assertSame(['product.type_id'], $field->getVariablePaths());
        self::assertSame('product_type(p.id)', $field->valueSql('p.id', 1)->getValueExpression());
    }

    /**
     * A product type field over a source of two products
     *
     * @param string $inputType
     * @return ConditionField
     */
    private function field(string $inputType = 'select'): ConditionField
    {
        return new ConditionField(
            new FakeValueSource(
                'product_type',
                [1 => ['simple'], 3 => ['virtual', 'gift']],
                ['simple' => 'Simple Product', 'virtual' => 'Virtual Product'],
                ['product.type_id']
            ),
            new OperatorDescriptors(PlacementFixture::operators(), new OperatorSetPolicy()),
            'product',
            'Product type',
            'product type',
            $inputType
        );
    }
}
