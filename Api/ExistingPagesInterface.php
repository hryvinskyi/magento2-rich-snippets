<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Magento\Framework\DB\Select;

/**
 * The storefront pages a store view has, kind by kind
 *
 * One definition for every count and every run over the catalog: product pages are the enabled products visible in
 * the catalog, in search or both, assigned to the store view's website; category pages are the active categories
 * under the store view's root category; CMS pages are the active CMS pages of the store view other than the ones
 * configured as its home page and its page-not-found page; the home page is one page, showing the configured home
 * CMS page. A kind such as "every other page" has no pages one can list.
 *
 * @api
 */
interface ExistingPagesInterface
{
    /**
     * The column every select of this interface yields: the ID of the entity each page shows
     */
    public const ENTITY_ID = 'entity_id';

    /**
     * A select of the pages of a kind in a store view, one row per page with the entity ID in ENTITY_ID
     *
     * @param string $kind
     * @param int $storeId
     * @return Select|null null for a kind whose pages cannot be listed
     * @throws \InvalidArgumentException when no kind has the code
     */
    public function select(string $kind, int $storeId): ?Select;
}
