<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Category;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;
use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\StockVisibility;
use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * The products each category page lists first, as list items with `position` (from 1), `name` and `url`
 *
 * Always the first page in the category's default sort order (its own, else the store's), with the page size of
 * the store's first listing mode, whatever a visitor's toolbar asks for: the storefront, the preview and the catalog
 * check list the same products. The products are those the category product index lists as visible in the catalog,
 * and, unless the store view shows out-of-stock products, in stock as the storefront reads the stock of the store
 * view's website; the positions count only the products listed.
 * Categories sharing a sort order are read in one query.
 */
class ListedProductsPart implements EntityDataPartInterface
{
    private const DEFAULT_SORT_PATH = 'catalog/frontend/default_sort_by';
    private const LIST_MODE_PATH = 'catalog/frontend/list_mode';
    private const GRID_SIZE_PATH = 'catalog/frontend/grid_per_page';
    private const LIST_SIZE_PATH = 'catalog/frontend/list_per_page';
    private const LIST_MODE_PREFIX = 'list';
    private const CATEGORY_SORT = 'default_sort_by';
    private const DEFAULT_SORT = 'position';
    private const DEFAULT_PAGE_SIZE = 12;
    private const ROUTE = 'catalog/product/view/id/%d';
    private const REWRITE_TYPE = 'product';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param TableMaintainer $tables
     * @param Visibility $visibility
     * @param ScopeConfigInterface $config
     * @param StockVisibility $stockVisibility
     * @param EavValueReader $categoryValues the category attribute values
     * @param EavStoreValueJoin $productValues the product attributes
     * @param EntityAddresses $addresses
     * @param ExpressionFactory $expressions
     * @param StockStatusJoin $stock
     * @param ListingOrderInterface $fallbackOrder the order of a sort option without one of its own
     * @param array<string,ListingOrderInterface> $orders by sort option code
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly TableMaintainer $tables,
        private readonly Visibility $visibility,
        private readonly ScopeConfigInterface $config,
        private readonly StockVisibility $stockVisibility,
        private readonly EavValueReader $categoryValues,
        private readonly EavStoreValueJoin $productValues,
        private readonly EntityAddresses $addresses,
        private readonly ExpressionFactory $expressions,
        private readonly StockStatusJoin $stock,
        private readonly ListingOrderInterface $fallbackOrder,
        private readonly array $orders = []
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
        $storeSort = $this->setting(self::DEFAULT_SORT_PATH, $storeId);
        $bySort = [];
        foreach ($this->categoryValues->values($ids, [self::CATEGORY_SORT], $storeId) as $id => $row) {
            $sort = $row[self::CATEGORY_SORT] ?? null;
            $sort = is_string($sort) && $sort !== '' ? $sort : $storeSort;
            $bySort[$sort !== '' ? $sort : self::DEFAULT_SORT][] = $id;
        }

        $listed = [];
        foreach ($bySort as $sortBy => $categoryIds) {
            foreach ($this->firstPage((string) $sortBy, $categoryIds, $storeId) as $row) {
                $listed[] = $row;
            }
        }
        $urls = $this->addresses->urls(
            self::REWRITE_TYPE,
            self::ROUTE,
            array_values(array_unique(array_map(static fn (array $row): int => $row['product_id'], $listed))),
            $storeId
        );
        $entries = [];
        foreach ($listed as $row) {
            $entries[$row['category_id']][] = [
                'position' => $row['position'],
                'name' => $row['name'],
                'url' => $urls[$row['product_id']],
            ];
        }

        return $entries;
    }

    /**
     * The first page of the categories sharing a sort order
     *
     * @param string $sortBy
     * @param list<int> $categoryIds
     * @param int $storeId
     * @return list<array{category_id: int, product_id: int, position: int, name: string|null}>
     */
    private function firstPage(string $sortBy, array $categoryIds, int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $inner = $connection->select()
            ->from(['listing' => $this->tables->getMainTable($storeId)], ['category_id', 'product_id'])
            ->where('listing.category_id IN (?)', $categoryIds)
            ->where('listing.store_id = ?', $storeId)
            ->where('listing.visibility IN (?)', $this->visibility->getVisibleInCatalogIds());
        if (!$this->stockVisibility->showsOutOfStock($storeId)) {
            $this->stock->limitToInStock($inner, 'listing.product_id', $storeId);
        }
        $order = ($this->orders[$sortBy] ?? $this->fallbackOrder)->expression($inner, $sortBy, $storeId);
        $name = $this->productValues->join($inner, 'listing.product_id', 'name', $storeId, 'listed_name');
        $inner->columns([
            'name' => $name,
            'listed_rank' => $this->expressions->create(['expression' => sprintf(
                'ROW_NUMBER() OVER (PARTITION BY listing.category_id ORDER BY %s ASC, listing.product_id ASC)',
                $order
            )]),
        ]);
        $select = $connection->select()
            ->from(['ranked' => $inner])
            ->where('ranked.listed_rank <= ?', $this->pageSize($storeId))
            ->order(['ranked.category_id ASC', 'ranked.listed_rank ASC']);
        $rows = [];
        foreach ($this->rows->all($select) as $row) {
            $rows[] = [
                'category_id' => (int) $row['category_id'],
                'product_id' => (int) $row['product_id'],
                'position' => (int) $row['listed_rank'],
                'name' => is_string($row['name']) && $row['name'] !== '' ? $row['name'] : null,
            ];
        }

        return $rows;
    }

    /**
     * The number of products on a category page's first page in the store's first listing mode
     *
     * @param int $storeId
     * @return int
     */
    private function pageSize(int $storeId): int
    {
        $mode = $this->setting(self::LIST_MODE_PATH, $storeId);
        $path = str_starts_with($mode, self::LIST_MODE_PREFIX) ? self::LIST_SIZE_PATH : self::GRID_SIZE_PATH;
        $size = (int) $this->setting($path, $storeId);

        return $size > 0 ? $size : self::DEFAULT_PAGE_SIZE;
    }

    /**
     * A store view's setting as text; empty when none is set
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    private function setting(string $path, int $storeId): string
    {
        $value = $this->config->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return is_scalar($value) ? (string) $value : '';
    }
}
