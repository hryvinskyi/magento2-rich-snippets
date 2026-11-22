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
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Review\Model\Review;

/**
 * Each product's approved reviews shown in the store view, newest first, as list items with `nickname`, `title`,
 * `text`, `date` (ISO 8601, the store view's day) and `rating` (the average of its votes out of 5, one decimal)
 *
 * When the templates take at most n reviews of the list, the database is asked for n per product, in one query for
 * the whole batch; the votes of the reviews read come with a second query.
 */
class LatestReviewsPart implements EntityDataPartInterface
{
    private const REVIEW_TABLE = 'review';
    private const DETAIL_TABLE = 'review_detail';
    private const STORE_TABLE = 'review_store';
    private const ENTITY_TABLE = 'review_entity';
    private const VOTE_TABLE = 'rating_option_vote';
    private const RATING_TABLE = 'rating';
    private const RATING_STORE_TABLE = 'rating_store';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param TimezoneInterface $timezone
     * @param ExpressionFactory $expressions
     * @param string $collectionPath the path of the list this part feeds, whose limit applies
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly TimezoneInterface $timezone,
        private readonly ExpressionFactory $expressions,
        private readonly string $collectionPath
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
        $ranked = $connection->select()
            ->from(['review' => $this->resource->getTableName(self::REVIEW_TABLE)], [
                'review_id',
                'product_id' => 'review.entity_pk_value',
                'created_at',
                'review_rank' => $this->expressions->create([
                    'expression' => 'ROW_NUMBER() OVER (PARTITION BY review.entity_pk_value'
                        . ' ORDER BY review.created_at DESC, review.review_id DESC)',
                ]),
            ])
            ->join(
                ['detail' => $this->resource->getTableName(self::DETAIL_TABLE)],
                'detail.review_id = review.review_id',
                ['nickname', 'title', 'detail']
            )
            ->join(
                ['store' => $this->resource->getTableName(self::STORE_TABLE)],
                $connection->quoteInto('store.review_id = review.review_id AND store.store_id = ?', $storeId),
                []
            )
            ->join(
                ['entity' => $this->resource->getTableName(self::ENTITY_TABLE)],
                $connection->quoteInto(
                    'entity.entity_id = review.entity_id AND entity.entity_code = ?',
                    Review::ENTITY_PRODUCT_CODE
                ),
                []
            )
            ->where('review.status_id = ?', Review::STATUS_APPROVED)
            ->where('review.entity_pk_value IN (?)', $ids);
        $select = $connection->select()->from(['ranked' => $ranked])->order(['product_id ASC', 'review_rank ASC']);
        $limit = $collectionLimits[$this->collectionPath] ?? null;
        if ($limit !== null) {
            $select->where('ranked.review_rank <= ?', $limit);
        }
        $rows = $this->rows->all($select);
        $ratings = $this->ratings(array_map(static fn (array $row): int => (int) $row['review_id'], $rows), $storeId);

        $entries = [];
        foreach ($rows as $row) {
            $entries[(int) $row['product_id']][] = [
                'nickname' => (string) $row['nickname'],
                'title' => (string) $row['title'],
                'text' => (string) $row['detail'],
                'date' => $this->timezone->scopeDate($storeId, (string) $row['created_at'], true)->format('Y-m-d'),
                'rating' => $ratings[(int) $row['review_id']] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * The average vote of each review over the ratings active in the store view, out of 5, one decimal
     *
     * @param list<int> $reviewIds
     * @param int $storeId
     * @return array<int, int|float> by review ID; a review without votes is left out
     */
    private function ratings(array $reviewIds, int $storeId): array
    {
        if ($reviewIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['vote' => $this->resource->getTableName(self::VOTE_TABLE)], [
                'review_id',
                'average' => 'AVG(vote.value)',
            ])
            ->join(
                ['rating' => $this->resource->getTableName(self::RATING_TABLE)],
                'rating.rating_id = vote.rating_id AND rating.is_active = 1',
                []
            )
            ->join(
                ['rating_store' => $this->resource->getTableName(self::RATING_STORE_TABLE)],
                $connection->quoteInto(
                    'rating_store.rating_id = vote.rating_id AND rating_store.store_id = ?',
                    $storeId
                ),
                []
            )
            ->where('vote.review_id IN (?)', $reviewIds)
            ->group('vote.review_id');
        $ratings = [];
        foreach ($this->rows->pairs($select) as $reviewId => $average) {
            $stars = round((float) $average, 1);
            $ratings[(int) $reviewId] = floor($stars) === $stars ? (int) $stars : $stars;
        }

        return $ratings;
    }
}
