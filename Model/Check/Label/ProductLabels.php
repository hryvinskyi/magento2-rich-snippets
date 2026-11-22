<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Names products by their name in the store view, their SKU and the name of their attribute set
 */
class ProductLabels implements PageLabelsInterface
{
    private const ATTRIBUTE_SET_TABLE = 'eav_attribute_set';

    /**
     * @param CollectionFactory $products
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly CollectionFactory $products,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function labels(array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $collection = $this->products->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIdFilter($ids);
        $rows = [];
        foreach ($collection->getItems() as $product) {
            $id = $product->getData('entity_id');
            $set = $product->getData('attribute_set_id');
            $name = $product->getData('name');
            $sku = $product->getData('sku');
            if (!is_numeric($id)) {
                continue;
            }
            $rows[(int) $id] = [
                'label' => is_string($name) ? $name : '',
                'sku' => is_string($sku) ? $sku : null,
                'set' => is_numeric($set) ? (int) $set : 0,
            ];
        }
        $sets = $this->setNames(array_values(array_unique(array_column($rows, 'set'))));
        $labels = [];
        foreach ($rows as $id => $row) {
            $labels[$id] = ['label' => $row['label'], 'sku' => $row['sku'], 'group' => $sets[$row['set']] ?? null];
        }

        return $labels;
    }

    /**
     * The names of attribute sets
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function setNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $names = [];
        $pairs = $connection->fetchPairs(
            $connection->select()
                ->from(
                    $this->resource->getTableName(self::ATTRIBUTE_SET_TABLE),
                    ['attribute_set_id', 'attribute_set_name']
                )
                ->where('attribute_set_id IN (?)', $ids)
        );
        foreach ($pairs as $id => $name) {
            if (is_string($name)) {
                $names[(int) $id] = $name;
            }
        }

        return $names;
    }
}
