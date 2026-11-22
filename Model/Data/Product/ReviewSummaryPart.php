<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\Review;

/**
 * Each product's approved reviews in the store view: `count` and the average `rating` out of 5 (one decimal)
 *
 * A product without an approved review in the store view has no entry, so both read as null and an aggregate
 * rating built from them is left out; a product whose reviews carry no rating has a count and no rating.
 */
class ReviewSummaryPart implements EntityDataPartInterface
{
    private const SUMMARY_TABLE = 'review_entity_summary';
    private const ENTITY_TABLE = 'review_entity';
    private const PERCENT_PER_STAR = 20;

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
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['summary' => $this->resource->getTableName(self::SUMMARY_TABLE)], [
                'entity_pk_value',
                'reviews_count',
                'rating_summary',
            ])
            ->join(
                ['entity' => $this->resource->getTableName(self::ENTITY_TABLE)],
                'entity.entity_id = summary.entity_type',
                []
            )
            ->where('entity.entity_code = ?', Review::ENTITY_PRODUCT_CODE)
            ->where('summary.store_id = ?', $storeId)
            ->where('summary.entity_pk_value IN (?)', $ids)
            ->where('summary.reviews_count > 0');
        $entries = [];
        foreach ($this->rows->all($select) as $row) {
            $summary = (int) $row['rating_summary'];
            $entries[(int) $row['entity_pk_value']] = [
                'count' => (int) $row['reviews_count'],
                'rating' => $summary > 0 ? $this->stars($summary) : null,
            ];
        }

        return $entries;
    }

    /**
     * A percentage as stars out of 5, one decimal, whole stars as an integer
     *
     * @param int $percent
     * @return int|float
     */
    private function stars(int $percent): int|float
    {
        $stars = round($percent / self::PERCENT_PER_STAR, 1);

        return floor($stars) === $stars ? (int) $stars : $stars;
    }
}
