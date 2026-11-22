<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Breadcrumb;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\StoreCatalogScope;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The category a product's breadcrumbs run through when the page's address names none: among the product's active
 * categories in the store view's tree, the deepest, ties broken by the category's position, then its ID
 *
 * The choice depends on the catalog alone, never on what the visitor browsed before, so every visitor, the page
 * cache and the catalog check see the same trail.
 */
class ProductCategories
{
    private const ASSIGNMENT_TABLE = 'catalog_category_product';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param EavStoreValueJoin $attributes the category attributes
     * @param StoreCatalogScope $scope
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool,
        private readonly EavStoreValueJoin $attributes,
        private readonly StoreCatalogScope $scope
    ) {
    }

    /**
     * The chosen category of each product, one query for any number of products
     *
     * @param list<int> $productIds
     * @param int $storeId
     * @return array<int, int> category ID by product ID; a product in no active category of the tree is left out
     */
    public function deepest(array $productIds, int $storeId): array
    {
        if ($productIds === []) {
            return [];
        }
        $select = $this->assignments($storeId)
            ->where('assignment.product_id IN (?)', $productIds)
            ->order([
                'assignment.product_id ASC',
                'category.level DESC',
                'category.position ASC',
                'category.entity_id ASC',
            ]);
        $chosen = [];
        foreach ($this->rows->all($select) as $row) {
            $chosen[(int) $row['product_id']] ??= (int) $row['category_id'];
        }

        return $chosen;
    }

    /**
     * Whether a product is in a category that is active and in the store view's tree
     *
     * @param int $productId
     * @param int $categoryId
     * @param int $storeId
     * @return bool
     */
    public function isAssigned(int $productId, int $categoryId, int $storeId): bool
    {
        $select = $this->assignments($storeId)
            ->where('assignment.product_id = ?', $productId)
            ->where('assignment.category_id = ?', $categoryId)
            ->limit(1);

        return $this->resource->getConnection()->fetchRow($select) !== false;
    }

    /**
     * The product assignments to active categories of the store view's tree
     *
     * @param int $storeId
     * @return Select
     */
    private function assignments(int $storeId): Select
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['assignment' => $this->resource->getTableName(self::ASSIGNMENT_TABLE)],
                ['product_id', 'category_id']
            )
            ->join(
                ['category' => $this->metadataPool->getMetadata(CategoryInterface::class)->getEntityTable()],
                'category.entity_id = assignment.category_id',
                []
            )
            ->where('category.path LIKE ?', '1/' . $this->scope->rootCategoryId($storeId) . '/%');
        $active = $this->attributes->join($select, 'category.entity_id', 'is_active', $storeId, 'active');

        return $select->where($active . ' = 1');
    }
}
