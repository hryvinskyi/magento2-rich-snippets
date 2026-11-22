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
use Hryvinskyi\UiFormElements\Api\CategoryTreeOptionsProviderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * The categories a product is in, as condition values of product pages, keyed by category ID
 *
 * A product is in each category it is assigned to and in every category above those, so a condition on a parent
 * category holds for every product below it. The options are the whole category tree.
 */
class ProductCategoryValues implements ConditionValueSourceInterface
{
    private const LINK_TABLE = 'catalog_category_product';
    private const CATEGORY_TABLE = 'catalog_category_entity';
    private const CATEGORIES_PART = 'categories';

    /**
     * @param ResourceConnection $resource
     * @param CategoryNames $names
     * @param CategoryTreeOptionsProviderInterface $tree
     * @param EntityDataSourceInterface $products the product pages' data source
     * @param ConditionValueSqlFactoryInterface $sql
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CategoryNames $names,
        private readonly CategoryTreeOptionsProviderInterface $tree,
        private readonly EntityDataSourceInterface $products,
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
        $ids = $this->products->get($entityId, $storeId, self::CATEGORIES_PART);

        return is_array($ids)
            ? array_values(array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $ids))
            : [];
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        $category = $this->resource->getTableName(self::CATEGORY_TABLE);
        $select = $this->resource->getConnection()->select()
            ->from(['rs_link' => $this->resource->getTableName(self::LINK_TABLE)], ['rs_link.product_id'])
            ->join(['rs_category' => $category], 'rs_category.entity_id = rs_link.category_id', [])
            ->join(
                ['rs_above' => $category],
                "rs_category.path = rs_above.path OR rs_category.path LIKE CONCAT(rs_above.path, '/%')",
                []
            )
            ->where('rs_link.product_id = ' . $entityIdExpression);

        return $this->sql->create($select, 'rs_above.entity_id');
    }

    /**
     * @inheritDoc
     */
    public function variablePaths(): array
    {
        return [];
    }
}
