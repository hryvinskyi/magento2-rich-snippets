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
 * The products in the position the merchant gave them in the category
 */
class PositionOrder implements ListingOrderInterface
{
    /**
     * @inheritDoc
     */
    public function expression(Select $select, string $sortBy, int $storeId): string
    {
        return 'listing.position';
    }
}
