<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\RichSnippets\Model\Snippet\ExistingStoreViewsInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * The store views of the shop's store table, read from the database each time
 */
class DatabaseStoreViews implements ExistingStoreViewsInterface
{
    private const TABLE = 'store';
    private const ID = 'store_id';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @inheritDoc
     */
    public function among(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $found = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), [self::ID])
                ->where(self::ID . ' IN (?)', $storeIds)
                ->order(self::ID . ' ASC')
        );

        $existing = [];
        foreach ($found as $id) {
            if (is_numeric($id)) {
                $existing[] = (int) $id;
            }
        }

        return $existing;
    }
}
