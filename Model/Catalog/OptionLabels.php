<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;

/**
 * The labels of attribute options kept in the option tables, in a store view, the default label standing in where
 * the store view has none of its own; one query for any number of options
 */
class OptionLabels
{
    private const OPTION_VALUE_TABLE = 'eav_attribute_option_value';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * The labels of the options
     *
     * @param list<int> $optionIds
     * @param int $storeId
     * @return array<int, string> by option ID; an option without a label is left out
     */
    public function labels(array $optionIds, int $storeId): array
    {
        if ($optionIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::OPTION_VALUE_TABLE), ['option_id', 'store_id', 'value'])
            ->where('option_id IN (?)', array_values(array_unique($optionIds)))
            ->where('store_id IN (?)', array_values(array_unique([0, $storeId])))
            ->order('store_id ASC');
        $labels = [];
        foreach ($this->rows->all($select) as $row) {
            $label = (string) $row['value'];
            if ($label !== '') {
                $labels[(int) $row['option_id']] = $label;
            }
        }

        return $labels;
    }
}
