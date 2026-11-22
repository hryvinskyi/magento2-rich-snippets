<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

use Magento\Framework\App\ResourceConnection;

/**
 * Names CMS pages by their title
 */
class CmsPageLabels implements PageLabelsInterface
{
    private const TABLE = 'cms_page';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @inheritDoc
     */
    public function labels(array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $labels = [];
        $pairs = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['page_id', 'title'])
                ->where('page_id IN (?)', $ids)
        );
        foreach ($pairs as $id => $title) {
            $labels[(int) $id] = ['label' => is_string($title) ? $title : '', 'sku' => null, 'group' => null];
        }

        return $labels;
    }
}
