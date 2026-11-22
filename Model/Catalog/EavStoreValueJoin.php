<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Joins a catalog attribute's value in a store view onto a select, the default value standing in where the store
 * view has none of its own
 *
 * The entity's value rows are found through the entity table's link field, so a schema whose value rows are keyed
 * by another column than the entity ID is read correctly too. An attribute that does not exist, or one stored in the
 * entity table itself, is a programming error and refused. The attributes looked up are kept until the request ends.
 */
class EavStoreValueJoin implements ResetAfterRequestInterface
{
    private const ATTRIBUTE_TABLE = 'eav_attribute';
    private const ENTITY_TYPE_TABLE = 'eav_entity_type';
    private const STATIC_BACKEND = 'static';

    /**
     * @var array<string, array{id: int, table: string}> the attributes looked up so far, by code
     */
    private array $attributes = [];

    /**
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param string $entityTypeCode the EAV entity type (`catalog_product`, `catalog_category`)
     * @param string $entityInterface the data interface whose entity metadata names the tables
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool,
        private readonly string $entityTypeCode,
        private readonly string $entityInterface
    ) {
    }

    /**
     * Join the attribute's value in the store view onto the select
     *
     * @param Select $select
     * @param string $entityIdExpression the SQL expression of the entity ID in the select
     * @param string $attributeCode
     * @param int $storeId
     * @param string $alias a prefix for the joined tables' aliases, unique within the select
     * @return string the SQL expression of the value
     * @throws \LogicException when the attribute does not exist or is stored in the entity table
     */
    public function join(
        Select $select,
        string $entityIdExpression,
        string $attributeCode,
        int $storeId,
        string $alias
    ): string {
        $attribute = $this->attribute($attributeCode);
        $metadata = $this->metadataPool->getMetadata($this->entityInterface);
        $linkField = $metadata->getLinkField();
        $link = $entityIdExpression;
        if ($linkField !== $metadata->getIdentifierField()) {
            $select->join(
                [$alias . '_entity' => $metadata->getEntityTable()],
                sprintf('%s_entity.%s = %s', $alias, $metadata->getIdentifierField(), $entityIdExpression),
                []
            );
            $link = sprintf('%s_entity.%s', $alias, $linkField);
        }
        $condition = '%1$s.%2$s = %3$s AND %1$s.attribute_id = %4$d AND %1$s.store_id = %5$d';
        $select->joinLeft(
            [$alias . '_default' => $attribute['table']],
            sprintf($condition, $alias . '_default', $linkField, $link, $attribute['id'], 0),
            []
        );
        if ($storeId === 0) {
            return $alias . '_default.value';
        }
        $select->joinLeft(
            [$alias . '_store' => $attribute['table']],
            sprintf($condition, $alias . '_store', $linkField, $link, $attribute['id'], $storeId),
            []
        );

        return sprintf('COALESCE(%1$s_store.value, %1$s_default.value)', $alias);
    }

    /**
     * The attribute's ID and value table
     *
     * @param string $code
     * @return array{id: int, table: string}
     * @throws \LogicException
     */
    private function attribute(string $code): array
    {
        if (isset($this->attributes[$code])) {
            return $this->attributes[$code];
        }
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['attribute' => $this->resource->getTableName(self::ATTRIBUTE_TABLE)], [
                    'attribute_id',
                    'backend_type',
                ])
                ->join(
                    ['entity_type' => $this->resource->getTableName(self::ENTITY_TYPE_TABLE)],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', $this->entityTypeCode)
                ->where('attribute.attribute_code = ?', $code)
        );
        $id = is_array($row) ? ($row['attribute_id'] ?? null) : null;
        $type = is_array($row) ? ($row['backend_type'] ?? null) : null;
        if (!is_numeric($id) || !is_string($type) || $type === '' || $type === self::STATIC_BACKEND) {
            throw new \LogicException(sprintf(
                'The %s attribute "%s" does not exist or has no value table of its own.',
                $this->entityTypeCode,
                $code
            ));
        }
        $table = $this->metadataPool->getMetadata($this->entityInterface)->getEntityTable() . '_' . $type;

        return $this->attributes[$code] = ['id' => (int) $id, 'table' => $table];
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->attributes = [];
    }
}
