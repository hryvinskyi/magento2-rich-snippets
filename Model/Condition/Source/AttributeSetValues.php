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
use Magento\Framework\DB\Select;

/**
 * Product attribute sets as condition values, keyed by attribute set ID, in the sets' own order
 *
 * A product's set is read from the product row its page's data source loads with the product's attributes.
 */
class AttributeSetValues implements ConditionValueSourceInterface
{
    private const PRODUCT_TABLE = 'catalog_product_entity';
    private const SET_TABLE = 'eav_attribute_set';
    private const ENTITY_TYPE_TABLE = 'eav_entity_type';
    private const PRODUCT_ENTITY_TYPE = 'catalog_product';
    private const ATTRIBUTES_PART = 'attributes';
    private const COLUMN = 'attribute_set_id';

    /**
     * @param ResourceConnection $resource
     * @param EntityDataSourceInterface $products the product pages' data source
     * @param ConditionValueSqlFactoryInterface $sql
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EntityDataSourceInterface $products,
        private readonly ConditionValueSqlFactoryInterface $sql
    ) {
    }

    /**
     * @inheritDoc
     */
    public function options(int $storeId): array
    {
        $options = [];
        foreach ($this->resource->getConnection()->fetchPairs($this->sets()) as $id => $name) {
            $options[] = ['value' => (string) $id, 'label' => is_scalar($name) ? (string) $name : ''];
        }

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function label(string $key, int $storeId): ?string
    {
        if (!ctype_digit($key)) {
            return null;
        }
        $names = $this->resource->getConnection()->fetchPairs(
            $this->sets()->where('attribute_set.attribute_set_id = ?', (int) $key)
        );
        $name = $names[(int) $key] ?? null;

        return is_scalar($name) ? (string) $name : null;
    }

    /**
     * @inheritDoc
     */
    public function entityValues(int $entityId, int $storeId): array
    {
        $entry = $this->products->get($entityId, $storeId, self::ATTRIBUTES_PART);
        $values = is_array($entry) && is_array($entry['values'] ?? null) ? $entry['values'] : [];
        $set = $values[self::COLUMN] ?? null;

        return is_numeric($set) ? [(string) (int) $set] : [];
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        $select = $this->resource->getConnection()->select()
            ->from(['rs_product' => $this->resource->getTableName(self::PRODUCT_TABLE)], ['rs_product.entity_id'])
            ->where('rs_product.entity_id = ' . $entityIdExpression);

        return $this->sql->create($select, 'rs_product.' . self::COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function variablePaths(): array
    {
        return [];
    }

    /**
     * A select of the product attribute sets' IDs and names, in the sets' order
     *
     * @return Select
     */
    private function sets(): Select
    {
        return $this->resource->getConnection()->select()
            ->from(
                ['attribute_set' => $this->resource->getTableName(self::SET_TABLE)],
                ['attribute_set.attribute_set_id', 'attribute_set.attribute_set_name']
            )
            ->join(
                ['entity_type' => $this->resource->getTableName(self::ENTITY_TYPE_TABLE)],
                'entity_type.entity_type_id = attribute_set.entity_type_id',
                []
            )
            ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
            ->order(['attribute_set.sort_order ASC', 'attribute_set.attribute_set_name ASC']);
    }
}
