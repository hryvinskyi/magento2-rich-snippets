<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Search;

use Magento\Framework\DB\Select;

/**
 * Names and addresses the pages of one kind for the page search
 *
 * The page search hands over the pages that exist as a select with one column, `entity_id`; the source joins what
 * it needs onto it and never widens it.
 */
interface PageSearchSourceInterface
{
    /**
     * The pages whose name holds a text, by name
     *
     * @param Select $pages the existing pages, one row per page with the entity ID in `entity_id`
     * @param string $query matched anywhere in the name, case-insensitively; empty for any page
     * @param int $storeId
     * @param int $limit the most pages
     * @return array<int, string> the names, by entity ID, in name order
     */
    public function names(Select $pages, string $query, int $storeId, int $limit): array;

    /**
     * The absolute canonical address of each page on the store view's storefront
     *
     * @param list<int> $ids
     * @param int $storeId
     * @return array<int, string> by entity ID
     */
    public function urls(array $ids, int $storeId): array;
}
