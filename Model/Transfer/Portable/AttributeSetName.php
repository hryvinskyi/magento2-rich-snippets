<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Portable;

use Hryvinskyi\RichSnippets\Api\PortableConditionValueInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Carries a product attribute set between shops by its name, which is unique among a shop's product attribute sets
 */
class AttributeSetName implements PortableConditionValueInterface
{
    private const SET_TABLE = 'eav_attribute_set';
    private const ENTITY_TYPE_TABLE = 'eav_entity_type';
    private const PRODUCT_ENTITY_TYPE = 'catalog_product';

    /**
     * @param ResourceConnection $resource
     * @param LabelledKeys $keys
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LabelledKeys $keys
    ) {
    }

    /**
     * @inheritDoc
     */
    public function labelOf(string $key, ?string $attribute): ?string
    {
        return $this->names()[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $key, string $label, ?string $attribute): ?string
    {
        return $this->keys->resolve($key, $label, $this->names());
    }

    /**
     * The product attribute sets' names, by ID
     *
     * @return array<string, string>
     */
    private function names(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['attribute_set' => $this->resource->getTableName(self::SET_TABLE)],
                ['attribute_set.attribute_set_id', 'attribute_set.attribute_set_name']
            )
            ->join(
                ['entity_type' => $this->resource->getTableName(self::ENTITY_TYPE_TABLE)],
                'entity_type.entity_type_id = attribute_set.entity_type_id',
                []
            )
            ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE);
        $names = [];
        foreach ($connection->fetchPairs($select) as $id => $name) {
            $names[(string) $id] = is_scalar($name) ? (string) $name : '';
        }

        return $names;
    }
}
