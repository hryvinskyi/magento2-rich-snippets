<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Source;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionValueSourceInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Sql\ConditionValueSqlFactoryInterface;
use Hryvinskyi\UiFormElements\Api\CategoryTreeOptionsProviderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Categories as condition values of category pages, keyed by category ID
 *
 * A category page has its own category as its value, and only that one: a condition on a parent category is about
 * the parent's page. The options are the whole category tree.
 */
class CategoryValues implements ConditionValueSourceInterface
{
    private const CATEGORY_TABLE = 'catalog_category_entity';

    /**
     * @param ResourceConnection $resource
     * @param CategoryNames $names
     * @param CategoryTreeOptionsProviderInterface $tree
     * @param ConditionValueSqlFactoryInterface $sql
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryNames $names,
        private readonly CategoryTreeOptionsProviderInterface $tree,
        private readonly ConditionValueSqlFactoryInterface $sql
    ) {
    }

    /**
     * @inheritDoc
     */
    public function options(int $storeId): array
    {
        return $this->tree->getOptions();
    }

    /**
     * @inheritDoc
     */
    public function label(string $key, int $storeId): ?string
    {
        return ctype_digit($key) ? $this->names->name((int) $key, $storeId) : null;
    }

    /**
     * @inheritDoc
     */
    public function entityValues(int $entityId, int $storeId): array
    {
        return [(string) $entityId];
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        $select = $this->resource->getConnection()->select()
            ->from(['rs_category' => $this->resource->getTableName(self::CATEGORY_TABLE)], ['rs_category.entity_id'])
            ->where('rs_category.entity_id = ' . $entityIdExpression);

        return $this->sql->create($select, 'rs_category.entity_id');
    }

    /**
     * @inheritDoc
     */
    public function variablePaths(): array
    {
        return [];
    }
}
