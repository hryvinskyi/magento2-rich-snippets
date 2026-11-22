<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Api\PlacementCounterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\ExpressionFactory;

/**
 * Counts a placement's pages with one query per kind over the pages the shop has
 *
 * The conditions become one predicate per kind, nested AND and OR as the groups say, so the database counts however
 * large the catalog is.
 */
class PlacementCounter implements PlacementCounterInterface
{
    private const PAGES = 'pages';
    private const EVERY_PAGE = '1 = 1';
    private const MATCHING = 'COALESCE(SUM(CASE WHEN (%s) THEN 1 ELSE 0 END), 0)';

    /**
     * @param ExistingPagesInterface $pages
     * @param PlacementConditions $conditions
     * @param ResourceConnection $resource
     * @param PlacementCountInterfaceFactory $counts
     * @param ExpressionFactory $expressions
     */
    public function __construct(
        private readonly ExistingPagesInterface $pages,
        private readonly PlacementConditions $conditions,
        private readonly ResourceConnection $resource,
        private readonly PlacementCountInterfaceFactory $counts,
        private readonly ExpressionFactory $expressions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function count(array $kinds, ConditionTreeInterface $conditions, int $storeId): array
    {
        $counts = [];
        foreach (array_values(array_unique($kinds)) as $kind) {
            $pages = $this->pages->select($kind, $storeId);
            if ($pages === null) {
                continue;
            }
            $predicate = $this->conditions->predicate(
                $conditions,
                $kind,
                self::PAGES . '.' . ExistingPagesInterface::ENTITY_ID,
                $storeId
            ) ?? self::EVERY_PAGE;
            $connection = $this->resource->getConnection();
            $row = $connection->fetchRow(
                $connection->select()->from([self::PAGES => $pages], [
                    'total' => 'COUNT(*)',
                    // an expression object: as text, a predicate holding aliased subqueries reads as a column name
                    'matching' => $this->expressions->create(['expression' => sprintf(self::MATCHING, $predicate)]),
                ])
            );
            $counts[$kind] = $this->counts->create([
                'matching' => is_array($row) && is_numeric($row['matching'] ?? null) ? (int) $row['matching'] : 0,
                'total' => is_array($row) && is_numeric($row['total'] ?? null) ? (int) $row['total'] : 0,
            ]);
        }

        return $counts;
    }
}
