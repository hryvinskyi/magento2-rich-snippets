<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Html;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterface;
use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Html\JsonLdBlock;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Hryvinskyi\RichSnippets\Model\Html\SchemaOrgMicrodata;
use Hryvinskyi\RichSnippets\Model\Html\StructuredData;
use Hryvinskyi\RichSnippets\Model\Html\StructuredDataScanner;
use Magento\Framework\DomDocument\DomDocumentFactory;
use PHPUnit\Framework\TestCase;

/**
 * The scanner finds every JSON-LD block of a page with the snippet that wrote it, reads its JSON without trusting
 * it, and lists the page's schema.org microdata items with their own properties
 */
class StructuredDataScannerTest extends TestCase
{
    public function testEveryJsonLdBlockIsFoundInPageOrderWithItsSnippetCode(): void
    {
        $html = $this->page(
            '<script type="application/ld+json" data-snippet="organization">{"@type":"OnlineStore","name":"Linden"}'
            . '</script>',
            '<script type="text/x-magento-init">{"*":{}}</script>'
            . '<script type="application/ld+json" data-snippet="website">{"@type":"WebSite","name":"Linden"}</script>'
            . '<script type="application/ld+json">{"@type":"Product","name":"Field satchel"}</script>'
        );

        $blocks = $this->scanner()->scan($html)->getBlocks();

        self::assertSame(
            [['organization', 'OnlineStore'], ['website', 'WebSite'], [null, 'Product']],
            array_map(
                static fn (JsonLdBlockInterface $block): array => [
                    $block->getCode(),
                    $block->getValue()['@type'] ?? null,
                ],
                $blocks
            )
        );
        self::assertSame('{"@type":"WebSite","name":"Linden"}', $blocks[1]->getJson());
    }

    public function testTheOrderAndCaseOfTheAttributesDoNotMatter(): void
    {
        $html = $this->page(
            '',
            '<SCRIPT data-snippet="product" TYPE="Application/LD+JSON; charset=utf-8">'
            . "\n  {\"@type\": \"Product\"}\n</SCRIPT>"
        );

        $blocks = $this->scanner()->scan($html)->getBlocks();

        self::assertCount(1, $blocks);
        self::assertSame('product', $blocks[0]->getCode());
        self::assertSame('{"@type": "Product"}', $blocks[0]->getJson());
        self::assertSame(['@type' => 'Product'], $blocks[0]->getValue());
    }

    public function testABlockWhoseTextIsNotJsonOfAnObjectHasNoValueButIsStillListed(): void
    {
        $html = $this->page('', '<script type="application/ld+json" data-snippet="broken">{"@type": "Product",</script>'
            . '<script type="application/ld+json">"just text"</script>'
            . '<script type="application/ld+json"></script>');

        $blocks = $this->scanner()->scan($html)->getBlocks();

        self::assertSame(
            [['broken', '{"@type": "Product",', null], [null, '"just text"', null], [null, '', null]],
            array_map(
                static fn (JsonLdBlockInterface $block): array => [
                    $block->getCode(),
                    $block->getJson(),
                    $block->getValue(),
                ],
                $blocks
            )
        );
    }

    public function testJsonNestedDeeperThanTheLimitHasNoValue(): void
    {
        $deep = str_repeat('[', 5) . str_repeat(']', 5);
        $html = $this->page('', '<script type="application/ld+json">' . $deep . '</script>');

        self::assertNull($this->scanner(4)->scan($html)->getBlocks()[0]->getValue());
        self::assertSame([[[[[]]]]], $this->scanner(6)->scan($html)->getBlocks()[0]->getValue());
    }

    public function testNonAsciiTextIsReadAsUtf8(): void
    {
        $html = '<html><body><script type="application/ld+json">{"name":"Lindenstraße 14 – Größe"}</script>'
            . '</body></html>';

        self::assertSame(
            ['name' => 'Lindenstraße 14 – Größe'],
            $this->scanner()->scan($html)->getBlocks()[0]->getValue()
        );
    }

    public function testMicrodataItemsAreListedWithTheirOwnPropertiesOnly(): void
    {
        $html = $this->page('', '<div itemscope itemtype="http://schema.org/Product">'
            . '<h1 itemprop="name">Field satchel</h1><img itemprop="image" src="a.jpg"/>'
            . '<div itemprop="offers" itemscope itemtype="http://schema.org/Offer">'
            . '<span itemprop="price">240.00</span><meta itemprop="priceCurrency" content="EUR"/></div>'
            . '<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">'
            . '<span itemprop="ratingValue">4.6</span></div>'
            . '<p itemprop="name description">Again</p></div>'
            . '<nav itemscope itemtype="https://schema.org/BreadcrumbList"><a itemprop="itemListElement">x</a></nav>'
            . '<div itemscope itemtype="https://example.org/Thing"><span itemprop="name">Not schema.org</span></div>');

        $items = $this->scanner()->scan($html)->getMicrodata();

        self::assertSame(
            [
                ['Product', ['name', 'image', 'offers', 'aggregateRating', 'description']],
                ['BreadcrumbList', ['itemListElement']],
            ],
            array_map(
                static fn (MicrodataItemInterface $item): array => [$item->getType(), $item->getProperties()],
                $items
            )
        );
    }

    public function testAPageWithoutStructuredDataHasNone(): void
    {
        $data = $this->scanner()->scan($this->page('<title>Cart</title>', '<p>Your cart is empty.</p>'));

        self::assertSame([], $data->getBlocks());
        self::assertSame([], $data->getMicrodata());
        self::assertSame([], $this->scanner()->scan('')->getBlocks());
    }

    public function testBrokenHtmlIsReadAsABrowserReadsIt(): void
    {
        $html = '<html><head><script type="application/ld+json" data-snippet="a">{"@type":"Thing"}</script>'
            . '<body><div><p>unclosed <b>tags<div itemscope itemtype="https://schema.org/Product"><i itemprop="name">'
            . 'x</div>';

        $data = $this->scanner()->scan($html);

        self::assertSame('a', $data->getBlocks()[0]->getCode());
        self::assertSame('Product', $data->getMicrodata()[0]->getType());
    }

    /**
     * A page with a head and a body
     *
     * @param string $head
     * @param string $body
     * @return string
     */
    private function page(string $head, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"/>' . $head . '</head><body>' . $body
            . '</body></html>';
    }

    /**
     * The scanner, with factories that build the module's own values
     *
     * @param positive-int $maxDepth
     * @return StructuredDataScanner
     */
    private function scanner(int $maxDepth = 128): StructuredDataScanner
    {
        $blocks = $this->createMock(JsonLdBlockInterfaceFactory::class);
        $blocks->method('create')->willReturnCallback(
            static fn (array $data): JsonLdBlockInterface => new JsonLdBlock(
                is_string($data['code'] ?? null) ? $data['code'] : null,
                is_string($data['json'] ?? null) ? $data['json'] : '',
                is_array($data['value'] ?? null) ? $data['value'] : null
            )
        );
        $items = $this->createMock(MicrodataItemInterfaceFactory::class);
        $items->method('create')->willReturnCallback(
            static fn (array $data): MicrodataItemInterface => new MicrodataItem(
                is_string($data['type'] ?? null) ? $data['type'] : '',
                array_values(array_filter(
                    is_array($data['properties'] ?? null) ? $data['properties'] : [],
                    'is_string'
                ))
            )
        );
        $structured = $this->createMock(StructuredDataInterfaceFactory::class);
        $structured->method('create')->willReturnCallback(
            static fn (array $data): StructuredDataInterface => new StructuredData(
                array_values(array_filter(
                    is_array($data['blocks'] ?? null) ? $data['blocks'] : [],
                    static fn (mixed $block): bool => $block instanceof JsonLdBlockInterface
                )),
                array_values(array_filter(
                    is_array($data['microdata'] ?? null) ? $data['microdata'] : [],
                    static fn (mixed $item): bool => $item instanceof MicrodataItemInterface
                ))
            )
        );
        $xpaths = $this->createMock(\DOMXPathFactory::class);
        $xpaths->method('create')->willReturnCallback(
            static fn (array $data): \DOMXPath => new \DOMXPath(
                $data['document'] instanceof \DOMDocument ? $data['document'] : new \DOMDocument()
            )
        );

        return new StructuredDataScanner(
            new DomDocumentFactory(),
            new SchemaOrgMicrodata(),
            $blocks,
            $items,
            $structured,
            $xpaths,
            $maxDepth
        );
    }
}
