<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Category;

use Magento\Framework\DB\Select;

/**
 * The order of one sort option of a category's product list, as an SQL expression over the listing select
 *
 * The listing select reads the category product index under the alias `listing`, with `product_id`; an order may
 * join what it needs onto it.
 */
interface ListingOrderInterface
{
    /**
     * The expression the products are ordered by, ascending
     *
     * @param Select $select
     * @param string $sortBy the sort option's code
     * @param int $storeId
     * @return string
     */
    public function expression(Select $select, string $sortBy, int $storeId): string;
}
