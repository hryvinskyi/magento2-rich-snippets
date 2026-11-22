<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Cache;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagsInterface;

/**
 * A page's tags, built by the same tag builder the snippet change cleaner cleans by
 */
class PageCacheTags implements PageCacheTagsInterface
{
    /**
     * @param PageCacheTagInterface $tags
     */
    public function __construct(private readonly PageCacheTagInterface $tags)
    {
    }

    /**
     * @inheritDoc
     */
    public function forPage(PageEntityInterface $page): array
    {
        return [
            $this->tags->forKind($page->getKind(), $page->getStoreId()),
            $this->tags->forStore($page->getStoreId()),
        ];
    }
}
