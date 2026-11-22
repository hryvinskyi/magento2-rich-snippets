<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The page cache tags of the snippets' output
 *
 * Every storefront page carries the tag of its kind in its store view and the tag of its store view, whether or not
 * a snippet is output there, so a change reaches a page that had no snippet before.
 *
 * @api
 */
interface PageCacheTagInterface
{
    /**
     * The tag of the pages of a kind in a store view
     *
     * @param string $kind
     * @param int $storeId
     * @return string
     */
    public function forKind(string $kind, int $storeId): string;

    /**
     * The tag of every page of a store view
     *
     * @param int $storeId
     * @return string
     */
    public function forStore(int $storeId): string;
}
