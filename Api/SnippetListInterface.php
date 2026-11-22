<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;

/**
 * Lists the shop's snippets as the Snippets page shows them
 *
 * Every row carries its flags from the flag provider pool and the snippets it includes and is included by, whether
 * or not those are listed.
 *
 * @api
 */
interface SnippetListInterface
{
    /**
     * The snippet list
     *
     * @param int|null $storeId only the snippets a store view outputs (those set for it or for every store view, and
     *     the building blocks); null for every snippet
     * @return SnippetListingInterface
     */
    public function list(?int $storeId): SnippetListingInterface;
}
