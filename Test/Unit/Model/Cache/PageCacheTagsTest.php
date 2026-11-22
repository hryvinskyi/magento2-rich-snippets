<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Cache;

use Hryvinskyi\RichSnippets\Model\Cache\PageCacheTag;
use Hryvinskyi\RichSnippets\Model\Cache\PageCacheTags;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use PHPUnit\Framework\TestCase;

/**
 * A page carries its kind's tag and its store view's tag, in that store view
 */
class PageCacheTagsTest extends TestCase
{
    public function testAPageCarriesItsKindAndStoreTags(): void
    {
        $tags = new PageCacheTags(new PageCacheTag());

        self::assertSame(
            ['rich_snippets_kind_product_2', 'rich_snippets_store_2'],
            $tags->forPage(new PageEntity('product', 12, 2))
        );
        self::assertSame(
            ['rich_snippets_kind_other_1', 'rich_snippets_store_1'],
            $tags->forPage(new PageEntity('other', null, 1))
        );
    }
}
