<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Cache;

use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;

/**
 * The page cache tags `rich_snippets_kind_<kind>_<store id>` and `rich_snippets_store_<store id>`
 *
 * A tag per store view keeps a change made for one store view from cleaning the pages of the others.
 */
class PageCacheTag implements PageCacheTagInterface
{
    private const KIND_PREFIX = 'rich_snippets_kind_';
    private const STORE_PREFIX = 'rich_snippets_store_';

    /**
     * @inheritDoc
     */
    public function forKind(string $kind, int $storeId): string
    {
        return self::KIND_PREFIX . $kind . '_' . $storeId;
    }

    /**
     * @inheritDoc
     */
    public function forStore(int $storeId): string
    {
        return self::STORE_PREFIX . $storeId;
    }
}
