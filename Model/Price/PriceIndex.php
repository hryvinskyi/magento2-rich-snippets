<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\StoreCatalogScope;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;

/**
 * Reads the platform's price index for the visitor's customer group and the store view's website, one query for
 * any number of products
 *
 * The index holds a product only when it is enabled and assigned to the website; its final price already includes
 * special prices, catalog price rules and the group's price for a quantity of one. The amounts are in the base
 * currency, as the catalog was entered.
 *
 * @phpstan-type IndexRow array{price: float, final_price: float, min_price: float, tax_class_id: int, type_id: string}
 * @phpstan-type ChildRow array{
 *     child_id: int, sku: string, price: float, final_price: float, min_price: float, tax_class_id: int, in_stock: bool
 * }
 */
class PriceIndex
{
    private const INDEX = 'catalog_product_index_price';
    private const SUPER_LINK_TABLE = 'catalog_product_super_link';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param PriceTableResolver $tables
     * @param DimensionFactory $dimensions
     * @param StoreCatalogScope $scope
     * @param VisitorPriceContext $visitor
     * @param MetadataPool $metadataPool
     * @param StockStatusJoin $stock
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly PriceTableResolver $tables,
        private readonly DimensionFactory $dimensions,
        private readonly StoreCatalogScope $scope,
        private readonly VisitorPriceContext $visitor,
        private readonly MetadataPool $metadataPool,
        private readonly StockStatusJoin $stock
    ) {
    }

    /**
     * The index row of each product
     *
     * @param list<int> $ids
     * @param int $storeId
     * @return array<int, IndexRow> by product ID; a product the index does not hold is left out
     */
    public function rows(array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['price' => $this->table($storeId)], [
                'entity_id',
                'price',
                'final_price',
                'min_price',
                'tax_class_id',
            ])
            ->join(
                ['product' => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
                'product.entity_id = price.entity_id',
                ['type_id']
            )
            ->where('price.entity_id IN (?)', $ids)
            ->where('price.website_id = ?', $this->scope->websiteId($storeId))
            ->where('price.customer_group_id = ?', $this->visitor->customerGroupId());
        $rows = [];
        foreach ($this->rows->all($select) as $row) {
            $rows[(int) $row['entity_id']] = [
                'price' => (float) $row['price'],
                'final_price' => (float) $row['final_price'],
                'min_price' => (float) $row['min_price'],
                'tax_class_id' => (int) $row['tax_class_id'],
                'type_id' => (string) $row['type_id'],
            ];
        }

        return $rows;
    }

    /**
     * The children of configurable products the index holds, with their stock status
     *
     * The status is the one the storefront reads for the store view's website.
     *
     * @param list<int> $parentIds
     * @param int $storeId
     * @return array<int, list<ChildRow>> by parent product ID, children by ID
     */
    public function children(array $parentIds, int $storeId): array
    {
        if ($parentIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $connection->select()
            ->from(['parent' => $metadata->getEntityTable()], ['parent_id' => 'parent.entity_id'])
            ->join(
                ['link' => $this->resource->getTableName(self::SUPER_LINK_TABLE)],
                'link.parent_id = parent.' . $metadata->getLinkField(),
                []
            )
            ->join(['child' => $metadata->getEntityTable()], 'child.entity_id = link.product_id', [
                'child_id' => 'child.entity_id',
                'sku' => 'child.sku',
            ])
            ->join(
                ['price' => $this->table($storeId)],
                $connection->quoteInto(
                    'price.entity_id = child.entity_id AND price.website_id = ?',
                    $this->scope->websiteId($storeId)
                ) . $connection->quoteInto(
                    ' AND price.customer_group_id = ?',
                    $this->visitor->customerGroupId()
                ),
                ['price', 'final_price', 'min_price', 'tax_class_id']
            )
            ->where('parent.entity_id IN (?)', $parentIds)
            ->where('parent.type_id = ?', Configurable::TYPE_CODE)
            ->order(['parent.entity_id ASC', 'child.entity_id ASC']);
        $this->stock->addStatus($select, 'child.entity_id', $storeId, 'in_stock');
        $children = [];
        foreach ($this->rows->all($select) as $row) {
            $children[(int) $row['parent_id']][] = [
                'child_id' => (int) $row['child_id'],
                'sku' => (string) $row['sku'],
                'price' => (float) $row['price'],
                'final_price' => (float) $row['final_price'],
                'min_price' => (float) $row['min_price'],
                'tax_class_id' => (int) $row['tax_class_id'],
                'in_stock' => (int) $row['in_stock'] === 1,
            ];
        }

        return $children;
    }

    /**
     * The index table of the store view's website and the visitor's customer group
     *
     * Its rows are those of every website and group when the index is not split; a select on it still filters by
     * `website_id` and `customer_group_id`.
     *
     * @param int $storeId
     * @return string
     */
    public function table(int $storeId): string
    {
        return $this->tables->resolve(self::INDEX, [
            $this->dimensions->create(
                WebsiteDimensionProvider::DIMENSION_NAME,
                (string) $this->scope->websiteId($storeId)
            ),
            $this->dimensions->create(
                CustomerGroupDimensionProvider::DIMENSION_NAME,
                (string) $this->visitor->customerGroupId()
            ),
        ]);
    }
}
