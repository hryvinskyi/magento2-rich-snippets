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
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;
use Hryvinskyi\RichSnippets\Model\Catalog\StoreCatalogScope;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The path from a store view's category tree to each category, as breadcrumb levels with `name` and `item` (the
 * category's canonical address), top level first, the category itself last
 *
 * The tree's root and the categories above it are no levels, and inactive categories are skipped; a category
 * outside the store view's tree has no path. Any number of categories take three queries.
 */
class CategoryTrails
{
    private const ROUTE = 'catalog/category/view/id/%d';
    private const REWRITE_TYPE = 'category';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param EavStoreValueJoin $attributes the category attributes
     * @param StoreCatalogScope $scope
     * @param EntityAddresses $addresses
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool,
        private readonly EavStoreValueJoin $attributes,
        private readonly StoreCatalogScope $scope,
        private readonly EntityAddresses $addresses
    ) {
    }

    /**
     * The path to each category
     *
     * @param list<int> $categoryIds
     * @param int $storeId
     * @return array<int, list<array{name: string, item: string}>> by category ID; a category outside the store
     *     view's tree is left out
     */
    public function trails(array $categoryIds, int $storeId): array
    {
        if ($categoryIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $table = $this->metadataPool->getMetadata(CategoryInterface::class)->getEntityTable();
        $prefix = '1/' . $this->scope->rootCategoryId($storeId) . '/';
        $levels = [];
        foreach ($this->rows->pairs(
            $connection->select()
                ->from($table, ['entity_id', 'path'])
                ->where('entity_id IN (?)', $categoryIds)
        ) as $id => $path) {
            if (str_starts_with((string) $path, $prefix)) {
                $levels[(int) $id] = array_map('intval', explode('/', substr((string) $path, strlen($prefix))));
            }
        }
        if ($levels === []) {
            return [];
        }

        $all = array_values(array_unique(array_merge(...array_values($levels))));
        $select = $connection->select()->from(['category' => $table], ['entity_id']);
        $name = $this->attributes->join($select, 'category.entity_id', 'name', $storeId, 'name');
        $active = $this->attributes->join($select, 'category.entity_id', 'is_active', $storeId, 'active');
        $select->columns(['name' => $name])->where('category.entity_id IN (?)', $all)->where($active . ' = 1');
        $names = [];
        foreach ($this->rows->pairs($select) as $id => $label) {
            $names[(int) $id] = (string) $label;
        }
        $urls = $this->addresses->urls(self::REWRITE_TYPE, self::ROUTE, array_keys($names), $storeId);

        $trails = [];
        foreach ($levels as $id => $path) {
            $trail = [];
            foreach ($path as $level) {
                if (isset($names[$level])) {
                    $trail[] = ['name' => $names[$level], 'item' => $urls[$level]];
                }
            }
            $trails[$id] = $trail;
        }

        return $trails;
    }
}
