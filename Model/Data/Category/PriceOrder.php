<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Category;

use Hryvinskyi\RichSnippets\Model\Catalog\StoreCatalogScope;
use Hryvinskyi\RichSnippets\Model\Price\PriceIndex;
use Hryvinskyi\RichSnippets\Model\Price\VisitorPriceContext;
use Magento\Framework\DB\Select;

/**
 * The products by the lowest price the visitor's customer group can buy them at
 */
class PriceOrder implements ListingOrderInterface
{
    /**
     * @param PriceIndex $index
     * @param StoreCatalogScope $scope
     * @param VisitorPriceContext $visitor
     */
    public function __construct(
        private readonly PriceIndex $index,
        private readonly StoreCatalogScope $scope,
        private readonly VisitorPriceContext $visitor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function expression(Select $select, string $sortBy, int $storeId): string
    {
        $select->joinLeft(
            ['sort_price' => $this->index->table($storeId)],
            sprintf(
                'sort_price.entity_id = listing.product_id AND sort_price.website_id = %d'
                    . ' AND sort_price.customer_group_id = %d',
                $this->scope->websiteId($storeId),
                $this->visitor->customerGroupId()
            ),
            []
        );

        return 'sort_price.min_price';
    }
}
