<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Page;

use Hryvinskyi\RichSnippets\Model\Page\Kind\NoEntityKindResolver;
use Hryvinskyi\RichSnippets\Model\Page\PageKindPool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every request is a page of exactly one kind: the kind owning its full action name, else the fallback kind
 */
class PageKindPoolTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function requests(): array
    {
        return [
            'the home page' => ['cms_index_index', 'home'],
            'a product page' => ['catalog_product_view', 'product'],
            'a category page' => ['catalog_category_view', 'category'],
            'a CMS page' => ['cms_page_view', 'cms'],
            'the page-not-found page' => ['cms_noroute_index', 'other'],
            'the review list of a product' => ['review_product_list', 'other'],
            'a request outside a page' => ['__', 'other'],
        ];
    }

    #[DataProvider('requests')]
    public function testARequestIsAPageOfTheKindOwningItsFullActionName(string $action, string $kind): void
    {
        self::assertSame($kind, $this->pool()->kindOfAction($action));
    }

    public function testTheKindsAreListedInTheirConfiguredOrder(): void
    {
        self::assertSame(['home', 'product', 'category', 'cms', 'other'], $this->pool()->getCodes());
        self::assertTrue($this->pool()->has('cms'));
        self::assertFalse($this->pool()->has('blog'));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No kind of page has the code "blog".');

        $this->pool()->get('blog');
    }

    public function testTwoKindsOwningOneFullActionNameAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The full action name "cms_page_view" belongs to the page kinds "cms" and "home"; it may belong to one.'
        );

        new PageKindPool([
            'cms' => new NoEntityKindResolver(['cms_page_view']),
            'home' => new NoEntityKindResolver(['cms_index_index', 'cms_page_view']),
            'other' => new NoEntityKindResolver(),
        ], 'other');
    }

    public function testAFallbackKindMissingFromTheKindsIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The fallback page kind "other" is not among the kinds of page.');

        new PageKindPool(['home' => new NoEntityKindResolver(['cms_index_index'])], 'other');
    }

    public function testAnItemThatIsNoKindOfPageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The page kind "home" is not a kind of page.');

        new PageKindPool(['home' => 'cms_index_index', 'other' => new NoEntityKindResolver()], 'other');
    }

    /**
     * The kinds the module ships, with their full action names
     *
     * @return PageKindPool
     */
    private function pool(): PageKindPool
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
