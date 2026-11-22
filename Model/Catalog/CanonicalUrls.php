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
 * The canonical addresses of catalog and CMS entities in a store view: the request path of each entity's own URL
 * rewrite, never one that runs through a category; one query for any number of entities
 */
class CanonicalUrls
{
    private const REWRITE_TABLE = 'url_rewrite';

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
     * The request path of each entity's own address
     *
     * @param string $entityType the URL rewrite entity type (`product`, `category`, `cms-page`)
     * @param list<int> $ids
     * @param int $storeId
     * @return array<int, string> by entity ID; an entity without a rewrite is left out
     */
    public function paths(string $entityType, array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::REWRITE_TABLE), ['entity_id', 'request_path'])
            ->where('entity_type = ?', $entityType)
            ->where('entity_id IN (?)', $ids)
            ->where('store_id = ?', $storeId)
            ->where('redirect_type = 0')
            ->where('metadata IS NULL')
            ->order('url_rewrite_id ASC');
        $paths = [];
        foreach ($this->rows->all($select) as $row) {
            $paths[(int) $row['entity_id']] ??= (string) $row['request_path'];
        }

        return $paths;
    }
}
