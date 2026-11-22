<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\RichSnippets\Model\Html\SchemaOrgMicrodata;
use Hryvinskyi\RichSnippets\Model\Storefront\MicrodataRemover;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only the items of the replaced schema.org types leave the page, with their properties and the items that are
 * properties of theirs; every other item, every other attribute and every tag that cannot be read stay as written
 */
class MicrodataRemoverTest extends TestCase
{
    private const PRODUCT_PAGE = '<body class="page" itemtype="http://schema.org/Product" itemscope="itemscope">'
        . '<ol itemscope itemtype="https://schema.org/BreadcrumbList">'
        . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
        . '<a itemprop="item" href="/"><span itemprop="name">Home</span></a><meta itemprop="position" content="1">'
        . '</li></ol>'
        . '<h1><span class="base" itemprop="name">Bag</span></h1>'
        . '<div class="price-box" itemprop="offers" itemscope itemtype="http://schema.org/Offer">'
        . '<meta itemprop="price" content="34.00" /><meta itemprop="priceCurrency" content="USD" /></div>'
        . '<div class="reviews" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">'
        . '<span itemprop="ratingValue">80</span></div>'
        . '<div class="value" itemprop="description">'
        . '<div itemscope itemtype="https://schema.org/Organization">'
                . '<span itemprop="name">Field Makers</span></div>'
        . '</div></body>';

    /**
     * @return array<string, array{string, list<string>, string}>
     */
    public static function pages(): array
    {
        return [
            'the product with its offer and rating, breadcrumbs and organization kept' => [
                self::PRODUCT_PAGE,
                ['Product', 'ProductGroup'],
                '<body class="page">'
                . '<ol itemscope itemtype="https://schema.org/BreadcrumbList">'
                . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
                . '<a itemprop="item" href="/"><span itemprop="name">Home</span></a>'
                . '<meta itemprop="position" content="1">'
                . '</li></ol>'
                . '<h1><span class="base">Bag</span></h1>'
                . '<div class="price-box"><meta content="34.00" /><meta content="USD" /></div>'
                . '<div class="reviews"><span>80</span></div>'
                . '<div class="value">'
                . '<div itemscope itemtype="https://schema.org/Organization">'
                . '<span itemprop="name">Field Makers</span></div>'
                . '</div></body>',
            ],
            'the breadcrumbs go too when a breadcrumb snippet replaces them' => [
                self::PRODUCT_PAGE,
                ['Product', 'ProductGroup', 'BreadcrumbList'],
                '<body class="page">'
                . '<ol>'
                . '<li>'
                . '<a href="/"><span>Home</span></a><meta content="1">'
                . '</li></ol>'
                . '<h1><span class="base">Bag</span></h1>'
                . '<div class="price-box"><meta content="34.00" /><meta content="USD" /></div>'
                . '<div class="reviews"><span>80</span></div>'
                . '<div class="value">'
                . '<div itemscope itemtype="https://schema.org/Organization">'
                . '<span itemprop="name">Field Makers</span></div>'
                . '</div></body>',
            ],
            'only the breadcrumbs when nothing replaces the product' => [
                self::PRODUCT_PAGE,
                ['BreadcrumbList'],
                str_replace(
                    '<ol itemscope itemtype="https://schema.org/BreadcrumbList">'
                    . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
                    . '<a itemprop="item" href="/"><span itemprop="name">Home</span></a>'
                    . '<meta itemprop="position" content="1">',
                    '<ol><li><a href="/"><span>Home</span></a><meta content="1">',
                    self::PRODUCT_PAGE
                ),
            ],
            'no replaced type leaves the page as it is' => [self::PRODUCT_PAGE, [], self::PRODUCT_PAGE],
            'a replaced item that is a property of a kept item leaves that item' => [
                '<ul itemscope itemtype="https://schema.org/ItemList">'
                . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/Product">'
                . '<span itemprop="name">Bag</span></li>'
                . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
                . '<span itemprop="name">Tote</span></li></ul>',
                ['Product'],
                '<ul itemscope itemtype="https://schema.org/ItemList">'
                . '<li><span>Bag</span></li>'
                . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
                . '<span itemprop="name">Tote</span></li></ul>',
            ],
            'any of several types names the item, and its id and references go with it' => [
                '<div itemscope itemid="#bag" itemref="extra"'
                . ' itemtype="https://schema.org/Thing https://schema.org/Product">'
                . '<span itemprop="name">Bag</span></div>',
                ['Product'],
                '<div><span>Bag</span></div>',
            ],
            'another vocabulary keeps its item of the same name' => [
                '<div itemscope itemtype="https://example.org/Product"><span itemprop="name">Bag</span></div>',
                ['Product'],
                '<div itemscope itemtype="https://example.org/Product"><span itemprop="name">Bag</span></div>',
            ],
            'unquoted, single-quoted and upper case' => [
                "<DIV ITEMSCOPE ITEMTYPE=https://schema.org/Product><SPAN ITEMPROP=name class=base>Bag</SPAN>"
                . "<link itemprop='image' href='https://shop.test/a.jpg'></DIV>",
                ['Product'],
                "<DIV><SPAN class=base>Bag</SPAN><link href='https://shop.test/a.jpg'></DIV>",
            ],
            'a value that only mentions the words' => [
                '<div itemscope itemtype="https://schema.org/Product">'
                . '<div title="itemprop=&quot;name&quot;" data-note=\'itemscope\'>itemprop="name" in text</div>'
                . '<a data-rule="a>b" itemprop="url" href="/x">x</a></div>',
                ['Product'],
                '<div>'
                . '<div title="itemprop=&quot;name&quot;" data-note=\'itemscope\'>itemprop="name" in text</div>'
                . '<a data-rule="a>b" href="/x">x</a></div>',
            ],
            'scripts, styles and comments are not read as markup' => [
                '<div itemscope itemtype="https://schema.org/Product">'
                . '<script type="text/x-magento-template"><div itemprop="name"></div></div></script>'
                . '<script>var s = "<span itemscope></div>";</script>'
                . '<style>.a::after{content:"</div>"}</style>'
                . '<!-- <div itemprop="sku"></div></div> -->'
                . '<p itemprop="description">Text</p></div><p itemprop="name">Kept</p>',
                ['Product'],
                '<div>'
                . '<script type="text/x-magento-template"><div itemprop="name"></div></div></script>'
                . '<script>var s = "<span itemscope></div>";</script>'
                . '<style>.a::after{content:"</div>"}</style>'
                . '<!-- <div itemprop="sku"></div></div> -->'
                . '<p>Text</p></div><p itemprop="name">Kept</p>',
            ],
            'nested elements of the same name close the item only at its own end' => [
                '<div itemscope itemtype="https://schema.org/Product"><div class="a"><div></div></div>'
                . '<span itemprop="sku">1</span></div><span itemprop="name">Kept</span>',
                ['Product'],
                '<div><div class="a"><div></div></div><span>1</span></div><span itemprop="name">Kept</span>',
            ],
            'empty elements do not hold the item open' => [
                '<div itemscope itemtype="https://schema.org/Product"><img itemprop="image" src="a.jpg"><br>'
                . '<meta itemscope itemprop="offers" itemtype="https://schema.org/Offer"></div>'
                . '<span itemprop="name">Kept</span>',
                ['Product'],
                '<div><img src="a.jpg"><br><meta></div><span itemprop="name">Kept</span>',
            ],
            'unclosed elements inside the item end with it' => [
                '<div itemscope itemtype="https://schema.org/Product"></span></p><ul><li><p>'
                . '<span itemprop="name">Bag</div><span itemprop="name">Kept</span>',
                ['Product'],
                '<div></span></p><ul><li><p><span>Bag</div><span itemprop="name">Kept</span>',
            ],
            'a tag that cannot be read stays as written' => [
                '<div itemscope itemtype="https://schema.org/Product">a < b <span itemprop="name" class="a>Bag',
                ['Product'],
                '<div>a < b <span itemprop="name" class="a>Bag',
            ],
            'an item that is never closed lasts to the end of the page' => [
                '<div itemscope itemtype="https://schema.org/Product"><span itemprop="name">Bag</span>'
                . '<p itemprop="description">Text',
                ['Product'],
                '<div><span>Bag</span><p>Text',
            ],
            'a page without microdata' => [
                '<html><head><title>Bag</title></head><body><p class="item">Product</p></body></html>',
                ['Product'],
                '<html><head><title>Bag</title></head><body><p class="item">Product</p></body></html>',
            ],
        ];
    }

    /**
     * @param string $html
     * @param list<string> $types
     * @param string $expected
     * @return void
     */
    #[DataProvider('pages')]
    public function testOnlyTheReplacedItemsLeaveThePage(string $html, array $types, string $expected): void
    {
        self::assertSame($expected, (new MicrodataRemover(new SchemaOrgMicrodata()))->strip($html, $types));
    }

    public function testAVeryLongScriptIsReadWithoutRunningOutOfBacktracking(): void
    {
        $script = '<script type="text/x-magento-init">' . str_repeat('{"a":"<b>itemprop</b>"},', 200000) . '</script>';

        self::assertSame(
            $script . '<div>',
            (new MicrodataRemover(new SchemaOrgMicrodata()))
                ->strip($script . '<div itemscope itemtype="https://schema.org/Offer">', ['Offer'])
        );
    }
}
