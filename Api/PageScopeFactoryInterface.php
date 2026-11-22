<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\ScopeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * Creates the scope a snippet of a page is evaluated in
 *
 * The roots (`product`, `category`, `page`, `store`, `reviews`, `breadcrumbs`) resolve, when a template first reads
 * them, to the values of the page's entity and store view. One scope serves one evaluation and the templates it
 * includes.
 *
 * @api
 */
interface PageScopeFactoryInterface
{
    /**
     * The scope of one evaluation on a page
     *
     * @param PageEntityInterface $page
     * @return ScopeInterface
     */
    public function create(PageEntityInterface $page): ScopeInterface;
}
