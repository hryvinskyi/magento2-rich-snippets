<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Lists and counts the pages that exist, for a run over all of them
 *
 * A kind whose pages cannot be listed has none here.
 */
class ExistingPageIds
{
    private const PAGES = 'pages';
    private const COUNTED = 'n';

    /**
     * @param ExistingPagesInterface $pages
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ExistingPagesInterface $pages,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * The entities the pages of a kind show in a store view, in ascending order
     *
     * @param string $kind
     * @param int $storeId
     * @return list<int|null> null for a page that shows no entity
     */
    public function ids(string $kind, int $storeId): array
    {
        $select = $this->pages->select($kind, $storeId);
        if ($select === null) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $ids = $connection->fetchCol(
            $connection->select()
                ->from([self::PAGES => $select], [ExistingPagesInterface::ENTITY_ID])
                ->order(ExistingPagesInterface::ENTITY_ID . ' ASC')
        );

        return array_values(array_map(
            static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null,
            $ids
        ));
    }

    /**
     * The entity the first page of a kind in a store view shows, by entity ID
     *
     * @param string $kind
     * @param int $storeId
     * @return int|null null when the store view has no page of the kind, or its first page shows no entity
     */
    public function first(string $kind, int $storeId): ?int
    {
        $select = $this->pages->select($kind, $storeId);
        if ($select === null) {
            return null;
        }
        $connection = $this->resource->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from([self::PAGES => $select], [ExistingPagesInterface::ENTITY_ID])
                ->order(ExistingPagesInterface::ENTITY_ID . ' ASC')
                ->limit(1)
        );

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * How many pages of a kind a store view has
     *
     * @param string $kind
     * @param int $storeId
     * @return int
     */
    public function count(string $kind, int $storeId): int
    {
        $select = $this->pages->select($kind, $storeId);

        return $select === null ? 0 : $this->counted($select, 'COUNT(*)');
    }

    /**
     * How many pages of a kind some store views have together, each page once
     *
     * @param string $kind
     * @param list<int> $storeIds
     * @return int
     */
    public function distinct(string $kind, array $storeIds): int
    {
        $selects = [];
        foreach ($storeIds as $storeId) {
            $select = $this->pages->select($kind, $storeId);
            if ($select !== null) {
                $selects[] = $select;
            }
        }
        if ($selects === []) {
            return 0;
        }

        $union = $this->resource->getConnection()->select();
        $union->union($selects, Select::SQL_UNION_ALL);

        return $this->counted(
            $union,
            'COUNT(DISTINCT COALESCE(' . self::PAGES . '.' . ExistingPagesInterface::ENTITY_ID . ', 0))'
        );
    }

    /**
     * A count over the rows of a select
     *
     * @param Select $select
     * @param string $count
     * @return int
     */
    private function counted(Select $select, string $count): int
    {
        $connection = $this->resource->getConnection();
        $value = $connection->fetchOne(
            $connection->select()->from([self::PAGES => $select], [self::COUNTED => $count])
        );

        return is_numeric($value) ? (int) $value : 0;
    }
}
