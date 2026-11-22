<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Runs a select and gives its rows as the database returns them: column values that are text, numbers or null
 */
class QueryRows
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Every row, each by column name
     *
     * @param Select $select
     * @return list<array<string, scalar|null>>
     */
    public function all(Select $select): array
    {
        $rows = [];
        foreach ($this->resource->getConnection()->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $columns = [];
            foreach ($row as $column => $value) {
                $columns[(string) $column] = is_scalar($value) ? $value : null;
            }
            $rows[] = $columns;
        }

        return $rows;
    }

    /**
     * The second column of each row by its first
     *
     * @param Select $select
     * @return array<int|string, scalar|null>
     */
    public function pairs(Select $select): array
    {
        $pairs = [];
        foreach ($this->resource->getConnection()->fetchPairs($select) as $key => $value) {
            $pairs[$key] = is_scalar($value) ? $value : null;
        }

        return $pairs;
    }
}
