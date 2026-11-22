<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Category;

use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The products by the value of the product attribute the sort option is named after, in the store view; a sort
 * option that names no product attribute keeps the merchant's positions
 */
class AttributeOrder implements ListingOrderInterface
{
    /**
     * @param ProductAttributes $attributes
     * @param EavStoreValueJoin $values the product attributes
     * @param MetadataPool $metadataPool
     * @param PositionOrder $positions
     */
    public function __construct(
        private readonly ProductAttributes $attributes,
        private readonly EavStoreValueJoin $values,
        private readonly MetadataPool $metadataPool,
        private readonly PositionOrder $positions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function expression(Select $select, string $sortBy, int $storeId): string
    {
        if (!$this->attributes->has($sortBy) || preg_match('/^[a-z][a-z0-9_]*$/', $sortBy) !== 1) {
            return $this->positions->expression($select, $sortBy, $storeId);
        }
        if (!$this->attributes->isStatic($sortBy)) {
            return $this->values->join($select, 'listing.product_id', $sortBy, $storeId, 'sort');
        }
        $select->join(
            ['sort_product' => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
            'sort_product.entity_id = listing.product_id',
            []
        );

        return 'sort_product.' . $sortBy;
    }
}
