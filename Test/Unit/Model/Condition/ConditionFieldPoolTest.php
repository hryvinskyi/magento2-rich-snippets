<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Condition;

use Hryvinskyi\RichSnippets\Model\Condition\ConditionField;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionFieldPool;
use Hryvinskyi\RichSnippets\Model\Condition\OperatorDescriptors;
use Hryvinskyi\RichSnippets\Model\Page\Kind\NoEntityKindResolver;
use Hryvinskyi\RichSnippets\Model\Page\PageKindPool;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FakeValueSource;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\UiFormElements\Model\OperatorSetPolicy;
use PHPUnit\Framework\TestCase;

/**
 * The condition fields by code, each offered for a kind of page that exists
 */
class ConditionFieldPoolTest extends TestCase
{
    public function testFieldsAreFoundByCodeAndByKind(): void
    {
        $type = $this->field('product', 'Product type');
        $set = $this->field('product', 'Attribute set');
        $page = $this->field('cms', 'Page');
        $pool = new ConditionFieldPool(
            $this->kinds(),
            ['product_type' => $type, 'attribute_set' => $set, 'cms_page' => $page]
        );

        self::assertSame($type, $pool->get('product_type'));
        self::assertTrue($pool->has('cms_page'));
        self::assertFalse($pool->has('brand'));
        self::assertSame(['product_type' => $type, 'attribute_set' => $set], $pool->forKind('product'));
        self::assertSame([], $pool->forKind('home'));
        self::assertSame(['product_type', 'attribute_set', 'cms_page'], $pool->getCodes());
    }

    public function testAnUnknownFieldIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No condition field has the code "brand".');

        (new ConditionFieldPool($this->kinds(), []))->get('brand');
    }

    public function testAFieldForAKindThatDoesNotExistIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The condition field "author" is offered for the kind of page "blog", which does not exist.'
        );

        new ConditionFieldPool($this->kinds(), ['author' => $this->field('blog', 'Author')]);
    }

    public function testAnItemThatIsNoFieldIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The condition field "brand" is not a condition field.');

        new ConditionFieldPool($this->kinds(), ['brand' => 'manufacturer']);
    }

    /**
     * A field of one subject for a kind of page
     *
     * @param string $kind
     * @param string $label
     * @return ConditionField
     */
    private function field(string $kind, string $label): ConditionField
    {
        return new ConditionField(
            new FakeValueSource($label),
            new OperatorDescriptors(PlacementFixture::operators(), new OperatorSetPolicy()),
            $kind,
            $label,
            strtolower($label)
        );
    }

    /**
     * @return PageKindPool
     */
    private function kinds(): PageKindPool
    {
        return new PageKindPool([
            'home' => new NoEntityKindResolver(['cms_index_index']),
            'product' => new NoEntityKindResolver(['catalog_product_view']),
            'cms' => new NoEntityKindResolver(['cms_page_view']),
            'other' => new NoEntityKindResolver(),
        ], 'other');
    }
}
