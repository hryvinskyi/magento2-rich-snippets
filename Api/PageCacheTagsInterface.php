<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * The page cache tags a storefront page carries for the snippets
 *
 * @api
 */
interface PageCacheTagsInterface
{
    /**
     * The tags of a page: its kind in its store view, then its store view
     *
     * A page carries them whether or not it outputs a snippet, so switching a snippet on for its kind tomorrow
     * cleans it.
     *
     * @param PageEntityInterface $page
     * @return list<string>
     */
    public function forPage(PageEntityInterface $page): array;
}
