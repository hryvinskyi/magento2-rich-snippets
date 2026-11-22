<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Counts fills with one grouped SQL query per attribute over the store view's product pages
 */
class SqlAttributeFillCounts implements AttributeFillCountsInterface
{
    private const KIND = 'product';
    private const PAGES = 'page';
    private const ALIAS = 'fill';

    /**
     * @param ResourceConnection $resource
     * @param ExistingPagesInterface $pages
     * @param EavStoreValueJoin $attributes the product attributes
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ExistingPagesInterface $pages,
        private readonly EavStoreValueJoin $attributes
    ) {
    }

    /**
     * @inheritDoc
     */
    public function filled(string $attributeCode, int $storeId): int
    {
        $select = $this->overPages($storeId);
        if ($select === null) {
            return 0;
        }
        $value = $this->attributes->join(
            $select,
            self::PAGES . '.' . ExistingPagesInterface::ENTITY_ID,
            $attributeCode,
            $storeId,
            self::ALIAS
        );
        $select->columns([
            'filled' => sprintf("SUM(CASE WHEN %1\$s IS NOT NULL AND %1\$s <> '' THEN 1 ELSE 0 END)", $value),
        ]);

        return (int) $this->resource->getConnection()->fetchOne($select);
    }

    /**
     * @inheritDoc
     */
    public function total(int $storeId): int
    {
        $select = $this->overPages($storeId);
        if ($select === null) {
            return 0;
        }
        $select->columns(['total' => 'COUNT(*)']);

        return (int) $this->resource->getConnection()->fetchOne($select);
    }

    /**
     * A select over the store view's product pages, without columns of its own; null when they cannot be listed
     *
     * @param int $storeId
     * @return Select|null
     */
    private function overPages(int $storeId): ?Select
    {
        $pages = $this->pages->select(self::KIND, $storeId);
        if ($pages === null) {
            return null;
        }

        return $this->resource->getConnection()->select()->from([self::PAGES => $pages], []);
    }
}
