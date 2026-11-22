<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Source;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionValueSourceInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Sql\ConditionValueSqlFactoryInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * CMS pages as condition values of CMS pages, keyed by page identifier, by title
 *
 * The identifier is what stays the same across store views and shops, so a condition holds for each store view's own
 * page with that identifier. The options are the store view's pages; a page's identifier is read from the CMS page
 * its data source loads.
 */
class CmsPageValues implements ConditionValueSourceInterface
{
    private const PAGE_TABLE = 'cms_page';
    private const PAGE_STORE_TABLE = 'cms_page_store';
    private const PAGE_PART = 'page';
    private const IDENTIFIER = 'identifier';

    /**
     * @param ResourceConnection $resource
     * @param EntityDataSourceInterface $pages the CMS pages' data source
     * @param ConditionValueSqlFactoryInterface $sql
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EntityDataSourceInterface $pages,
        private readonly ConditionValueSqlFactoryInterface $sql
    ) {
    }

    /**
     * @inheritDoc
     */
    public function options(int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $titles = $connection->fetchPairs(
            $connection->select()
                ->from(['page' => $this->resource->getTableName(self::PAGE_TABLE)], [
                    'identifier' => 'page.identifier',
                    'title' => 'MIN(page.title)',
                ])
                ->join(
                    ['page_store' => $this->resource->getTableName(self::PAGE_STORE_TABLE)],
                    'page_store.page_id = page.page_id',
                    []
                )
                ->where('page_store.store_id IN (?)', array_values(array_unique([0, $storeId])))
                ->group('page.identifier')
                ->order(['title ASC', 'page.identifier ASC'])
        );
        $options = [];
        foreach ($titles as $identifier => $title) {
            $options[] = ['value' => (string) $identifier, 'label' => is_scalar($title) ? (string) $title : ''];
        }

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function label(string $key, int $storeId): ?string
    {
        $connection = $this->resource->getConnection();
        $titles = $connection->fetchCol(
            $connection->select()
                ->from(['page' => $this->resource->getTableName(self::PAGE_TABLE)], ['page.title'])
                ->join(
                    ['page_store' => $this->resource->getTableName(self::PAGE_STORE_TABLE)],
                    'page_store.page_id = page.page_id',
                    []
                )
                ->where('page.identifier = ?', $key)
                ->where('page_store.store_id IN (?)', [0, $storeId])
                ->order('page_store.store_id DESC')
                ->limit(1)
        );
        $title = $titles[0] ?? null;

        return is_scalar($title) ? (string) $title : null;
    }

    /**
     * @inheritDoc
     */
    public function entityValues(int $entityId, int $storeId): array
    {
        $entry = $this->pages->get($entityId, $storeId, self::PAGE_PART);
        $identifier = is_array($entry) ? ($entry[self::IDENTIFIER] ?? null) : null;

        return is_string($identifier) && $identifier !== '' ? [$identifier] : [];
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        $select = $this->resource->getConnection()->select()
            ->from(['rs_page' => $this->resource->getTableName(self::PAGE_TABLE)], ['rs_page.page_id'])
            ->where('rs_page.page_id = ' . $entityIdExpression);

        return $this->sql->create($select, 'rs_page.' . self::IDENTIFIER);
    }

    /**
     * @inheritDoc
     */
    public function variablePaths(): array
    {
        return [];
    }
}
