<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Each product's stock status as the schema.org availability URL, as `availability`
 *
 * The status is the one the storefront reads for the store view's website, through the same join the listings and
 * prices use, so a website selling from a stock of its own gets that stock's answer. A product the stock does not
 * hold is out of stock; an ID no product has gets no entry.
 */
class StockPart implements EntityDataPartInterface
{
    private const IN_STOCK = 'https://schema.org/InStock';
    private const OUT_OF_STOCK = 'https://schema.org/OutOfStock';
    private const PRODUCT_ALIAS = 'product';
    private const STATUS_COLUMN = 'in_stock';

    /**
     * @param StockStatusJoin $stock
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly StockStatusJoin $stock,
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        if ($ids === []) {
            return [];
        }
        $idColumn = self::PRODUCT_ALIAS . '.entity_id';
        $select = $this->resource->getConnection()->select()
            ->from(
                [self::PRODUCT_ALIAS => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
                ['entity_id' => $idColumn]
            )
            ->where($idColumn . ' IN (?)', $ids);
        $this->stock->addStatus($select, $idColumn, $storeId, self::STATUS_COLUMN);
        $entries = [];
        foreach ($this->rows->all($select) as $row) {
            $entries[(int) $row['entity_id']] = [
                'availability' => (int) $row[self::STATUS_COLUMN] === 1 ? self::IN_STOCK : self::OUT_OF_STOCK,
            ];
        }

        return $entries;
    }
}
