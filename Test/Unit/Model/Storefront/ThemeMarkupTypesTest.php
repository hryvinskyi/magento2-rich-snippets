<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Hryvinskyi\RichSnippets\Model\Observation\ThemeMarkupObservation;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupTypes;
use PHPUnit\Framework\TestCase;

/**
 * The theme's markup a page test notes is only its items of the types a snippet replaces: a product page that keeps
 * an Organization item once its Product item is removed carries no theme Product markup
 */
class ThemeMarkupTypesTest extends TestCase
{
    public function testOnlyTheItemsOfTheProductTypesAreKeptInPageOrder(): void
    {
        $items = [
            new MicrodataItem('Organization', ['name']),
            new MicrodataItem('Product', ['name', 'offers']),
            new MicrodataItem('BreadcrumbList', ['itemListElement']),
            new MicrodataItem('ProductGroup', ['hasVariant']),
        ];

        $kept = $this->types()->itemsAmong($items);

        self::assertSame([$items[1], $items[3]], $kept);
    }

    public function testAPageWithAProductAndAnOrganizationItemIsObservedWithItsProductMarkup(): void
    {
        $product = new MicrodataItem('Product', ['name', 'offers']);
        $observation = $this->observation(
            $this->types()->itemsAmong([new MicrodataItem('Organization', ['name']), $product])
        );

        self::assertTrue($observation->isFound());
        self::assertSame([$product], $observation->getItems());
    }

    public function testAPageLeftWithAnOrganizationItemAloneIsObservedWithoutThemeMarkup(): void
    {
        $observation = $this->observation($this->types()->itemsAmong([new MicrodataItem('Organization', ['name'])]));

        self::assertFalse($observation->isFound());
        self::assertSame([], $observation->getItems());
    }

    public function testTheTypesAreListedOnceWithoutEmptyOnes(): void
    {
        $types = new ThemeMarkupTypes(
            ['product' => 'Product', 'again' => 'Product', 'none' => '', 'group' => 'ProductGroup']
        );

        self::assertSame(['Product', 'ProductGroup'], $types->all());
    }

    /**
     * The types the module ships
     *
     * @return ThemeMarkupTypes
     */
    private function types(): ThemeMarkupTypes
    {
        return new ThemeMarkupTypes(['product' => 'Product', 'product_group' => 'ProductGroup']);
    }

    /**
     * An observation of a product page with the items given
     *
     * @param list<MicrodataItemInterface> $items
     * @return ThemeMarkupObservation
     */
    private function observation(array $items): ThemeMarkupObservation
    {
        return new ThemeMarkupObservation(1, 'product', '/field-satchel.html', $items, 1767225600);
    }
}
