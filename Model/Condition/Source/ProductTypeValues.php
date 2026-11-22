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
use Magento\Catalog\Api\ProductTypeListInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Product types as condition values, keyed by type ID (`simple`, `configurable`), in the order the shop registers them
 *
 * A product's type is read from the product row its page's data source loads with the product's attributes.
 */
class ProductTypeValues implements ConditionValueSourceInterface
{
    private const PRODUCT_TABLE = 'catalog_product_entity';
    private const ATTRIBUTES_PART = 'attributes';
    private const COLUMN = 'type_id';

    /**
     * @var array<string, string>|null the registered types' labels by type ID
     */
    private ?array $types = null;

    /**
     * @param ResourceConnection $resource
     * @param ProductTypeListInterface $typeList
     * @param EntityDataSourceInterface $products the product pages' data source
     * @param ConditionValueSqlFactoryInterface $sql
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductTypeListInterface $typeList,
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
        foreach ($this->types() as $type => $label) {
            $options[] = ['value' => $type, 'label' => $label];
        }

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function label(string $key, int $storeId): ?string
    {
        return $this->types()[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function entityValues(int $entityId, int $storeId): array
    {
        $entry = $this->products->get($entityId, $storeId, self::ATTRIBUTES_PART);
        $values = is_array($entry) && is_array($entry['values'] ?? null) ? $entry['values'] : [];
        $type = $values[self::COLUMN] ?? null;

        return is_scalar($type) && (string) $type !== '' ? [(string) $type] : [];
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
     * The registered product types' labels by type ID
     *
     * @return array<string, string>
     */
    private function types(): array
    {
        if ($this->types === null) {
            $this->types = [];
            foreach ($this->typeList->getProductTypes() as $type) {
                $this->types[(string) $type->getName()] = (string) $type->getLabel();
            }
        }

        return $this->types;
    }
}
