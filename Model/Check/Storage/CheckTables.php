<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Storage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * The connection and the tables the results of the catalog checks are kept in
 */
class CheckTables
{
    public const RUN = 'hryvinskyi_rich_snippets_check_run';
    public const FINDING = 'hryvinskyi_rich_snippets_check_finding';
    public const ITEM = 'hryvinskyi_rich_snippets_check_finding_item';
    public const AGGREGATE = 'hryvinskyi_rich_snippets_check_aggregate';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * The connection
     *
     * @return AdapterInterface
     */
    public function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * The real name of a table
     *
     * @param string $table one of the constants
     * @return string
     */
    public function name(string $table): string
    {
        return $this->resource->getTableName($table);
    }
}
