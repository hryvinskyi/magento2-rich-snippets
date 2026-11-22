<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The attributes each configurable product's variants differ by, read for many products with one query
 */
class ConfigurableAttributes
{
    private const SUPER_ATTRIBUTE_TABLE = 'catalog_product_super_attribute';
    private const ATTRIBUTE_TABLE = 'eav_attribute';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * The configurable attributes of each product, by code, in the product's order
     *
     * A product that is not configurable is left out.
     *
     * @param list<int> $productIds
     * @return array<int, array<string, int>> attribute ID by code, by product ID
     */
    public function byProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $connection->select()
            ->from(['product' => $metadata->getEntityTable()], ['product_id' => 'product.entity_id'])
            ->join(
                ['super' => $this->resource->getTableName(self::SUPER_ATTRIBUTE_TABLE)],
                'super.product_id = product.' . $metadata->getLinkField(),
                ['attribute_id']
            )
            ->join(
                ['attribute' => $this->resource->getTableName(self::ATTRIBUTE_TABLE)],
                'attribute.attribute_id = super.attribute_id',
                ['attribute_code']
            )
            ->where('product.entity_id IN (?)', $productIds)
            ->order(['product.entity_id ASC', 'super.position ASC', 'super.attribute_id ASC']);
        $attributes = [];
        foreach ($this->rows->all($select) as $row) {
            $attributes[(int) $row['product_id']][(string) $row['attribute_code']] = (int) $row['attribute_id'];
        }

        return $attributes;
    }
}
