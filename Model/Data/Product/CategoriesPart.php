<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;

/**
 * The IDs of the categories each product is in: the ones it is assigned to and every category above them, from the
 * categories' paths, in ascending order
 */
class CategoriesPart implements EntityDataPartInterface
{
    private const LINK_TABLE = 'catalog_category_product';
    private const CATEGORY_TABLE = 'catalog_category_entity';
    private const PATH_SEPARATOR = '/';

    /**
     * @param ResourceConnection $resource
     * @param QueryRows $rows
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QueryRows $rows
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
        $select = $this->resource->getConnection()->select()
            ->from(['link' => $this->resource->getTableName(self::LINK_TABLE)], ['product_id' => 'link.product_id'])
            ->join(
                ['category' => $this->resource->getTableName(self::CATEGORY_TABLE)],
                'category.entity_id = link.category_id',
                ['path' => 'category.path']
            )
            ->where('link.product_id IN (?)', $ids);
        $categories = [];
        foreach ($this->rows->all($select) as $row) {
            $product = is_numeric($row['product_id'] ?? null) ? (int) $row['product_id'] : 0;
            $path = is_string($row['path'] ?? null) ? $row['path'] : '';
            foreach (explode(self::PATH_SEPARATOR, $path) as $id) {
                if (ctype_digit($id)) {
                    $categories[$product][(int) $id] = true;
                }
            }
        }
        $entries = [];
        foreach ($categories as $product => $found) {
            $list = array_keys($found);
            sort($list);
            $entries[$product] = array_map('strval', $list);
        }

        return $entries;
    }
}
