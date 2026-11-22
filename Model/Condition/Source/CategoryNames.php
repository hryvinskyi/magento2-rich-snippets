<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Source;

use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The name of a category in a store view
 *
 * A category with no name of its own in the store view takes its default name.
 */
class CategoryNames
{
    private const CATEGORY_TABLE = 'catalog_category_entity';

    /**
     * @param ResourceConnection $resource
     * @param EavStoreValueJoin $attributes the category attributes
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavStoreValueJoin $attributes
    ) {
    }

    /**
     * The name of one category
     *
     * @param int $id
     * @param int $storeId
     * @return string|null null when no category has the ID
     */
    public function name(int $id, int $storeId): ?string
    {
        return $this->pairs($this->names($storeId)->where('category.entity_id = ?', $id))[$id] ?? null;
    }

    /**
     * A select of category IDs and names in the store view
     *
     * @param int $storeId
     * @return Select
     */
    private function names(int $storeId): Select
    {
        $select = $this->resource->getConnection()->select()
            ->from(['category' => $this->resource->getTableName(self::CATEGORY_TABLE)], ['category.entity_id']);
        $name = $this->attributes->join($select, 'category.entity_id', 'name', $storeId, 'name');

        return $select->columns(['name' => $name]);
    }

    /**
     * The names a select yields, by category ID
     *
     * @param Select $select
     * @return array<int, string>
     */
    private function pairs(Select $select): array
    {
        $names = [];
        foreach ($this->resource->getConnection()->fetchPairs($select) as $id => $name) {
            $names[(int) $id] = is_scalar($name) ? (string) $name : '';
        }

        return $names;
    }
}
