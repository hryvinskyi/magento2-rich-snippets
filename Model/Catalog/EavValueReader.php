<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Reads catalog attribute values of many entities in a store view at once, the default value standing in where the
 * store view has none of its own
 *
 * Columns of the entity table (`sku`, `type_id` …) come with one query on the entity table, which always runs; the
 * other attributes with one query per value table they are stored in, whatever the number of entities. An attribute
 * code the entity type does not have is ignored. Value rows are found through the entity table's link field.
 *
 * The entity type's attributes are looked up once, and again the first time a code asked for is not among them,
 * so an attribute created while the process runs is read too; a code still missing then is not looked up again.
 * What was looked up is dropped after each request, so an attribute deleted and created again is read by its new ID.
 */
class EavValueReader implements ResetAfterRequestInterface
{
    private const ATTRIBUTE_TABLE = 'eav_attribute';
    private const ENTITY_TYPE_TABLE = 'eav_entity_type';
    private const STATIC_BACKEND = 'static';

    /**
     * @var array<string, array{id: int, backend: string}>|null the entity type's attributes, by code
     */
    private ?array $attributes = null;

    /**
     * @var array<string, true> codes asked for that the entity type did not have when looked up again
     */
    private array $absent = [];

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param string $entityTypeCode the EAV entity type (`catalog_product`, `catalog_category`)
     * @param string $entityInterface the data interface whose entity metadata names the tables
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool,
        private readonly string $entityTypeCode,
        private readonly string $entityInterface
    ) {
    }

    /**
     * The entity table's row and the attribute values of each entity
     *
     * @param list<int> $ids
     * @param list<string> $codes
     * @param int $storeId
     * @return array<int, array<string, mixed>> values by attribute code (and entity table column), by entity ID;
     *     an entity that does not exist is left out
     */
    public function values(array $ids, array $codes, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $metadata = $this->metadataPool->getMetadata($this->entityInterface);
        $idField = $metadata->getIdentifierField();
        $linkField = $metadata->getLinkField();
        $values = [];
        $links = [];
        foreach ($this->rows->all(
            $connection->select()
                ->from($metadata->getEntityTable())
                ->where($idField . ' IN (?)', $ids)
        ) as $row) {
            $id = (int) $row[$idField];
            $values[$id] = $row;
            $links[(int) $row[$linkField]] = $id;
        }
        if ($links === []) {
            return [];
        }

        foreach ($this->byBackend($codes) as $backend => $attributes) {
            $select = $connection->select()
                ->from($metadata->getEntityTable() . '_' . $backend, [$linkField, 'attribute_id', 'store_id', 'value'])
                ->where($linkField . ' IN (?)', array_keys($links))
                ->where('attribute_id IN (?)', array_keys($attributes))
                ->where('store_id IN (?)', array_values(array_unique([0, $storeId])))
                ->order('store_id ASC');
            foreach ($this->rows->all($select) as $row) {
                $id = $links[(int) $row[$linkField]] ?? null;
                $code = $attributes[(int) $row['attribute_id']] ?? null;
                if ($id !== null && $code !== null) {
                    $values[$id][$code] = $row['value'];
                }
            }
        }

        return $values;
    }

    /**
     * The codes that have a value table, grouped by it
     *
     * @param list<string> $codes
     * @return array<string, array<int, string>> codes by attribute ID, by backend type
     */
    private function byBackend(array $codes): array
    {
        $attributes = $this->attributes();
        $unknown = array_diff($codes, array_keys($attributes), array_keys($this->absent));
        if ($unknown !== []) {
            $this->attributes = null;
            $attributes = $this->attributes();
            foreach (array_diff($unknown, array_keys($attributes)) as $code) {
                $this->absent[$code] = true;
            }
        }
        $grouped = [];
        foreach ($codes as $code) {
            $attribute = $attributes[$code] ?? null;
            if ($attribute !== null && $attribute['backend'] !== self::STATIC_BACKEND) {
                $grouped[$attribute['backend']][$attribute['id']] = $code;
            }
        }

        return $grouped;
    }

    /**
     * The entity type's attributes, looked up once
     *
     * @return array<string, array{id: int, backend: string}>
     */
    private function attributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }
        $connection = $this->resource->getConnection();
        $rows = $this->rows->all(
            $connection->select()
                ->from(['attribute' => $this->resource->getTableName(self::ATTRIBUTE_TABLE)], [
                    'attribute_id',
                    'attribute_code',
                    'backend_type',
                ])
                ->join(
                    ['entity_type' => $this->resource->getTableName(self::ENTITY_TYPE_TABLE)],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', $this->entityTypeCode)
        );
        $attributes = [];
        foreach ($rows as $row) {
            $attributes[(string) $row['attribute_code']] = [
                'id' => (int) $row['attribute_id'],
                'backend' => (string) $row['backend_type'],
            ];
        }

        return $this->attributes = $attributes;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->attributes = null;
        $this->absent = [];
    }
}
