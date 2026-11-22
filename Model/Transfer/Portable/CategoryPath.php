<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Portable;

use Hryvinskyi\RichSnippets\Api\PortableConditionValueInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Magento\Framework\App\ResourceConnection;

/**
 * Carries a category between shops by its path of default names from its root category down
 * (`Default Category / Bags / Totes`), since two categories may share a name but rarely a whole path
 */
class CategoryPath implements PortableConditionValueInterface
{
    private const CATEGORY_TABLE = 'catalog_category_entity';
    private const TREE_TOP_ID = '1';
    private const PATH_SEPARATOR = '/';
    private const LABEL_SEPARATOR = ' / ';
    private const DEFAULT_STORE = 0;

    /**
     * @param ResourceConnection $resource
     * @param EavStoreValueJoin $attributes the category attributes
     * @param LabelledKeys $keys
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavStoreValueJoin $attributes,
        private readonly LabelledKeys $keys
    ) {
    }

    /**
     * @inheritDoc
     */
    public function labelOf(string $key, ?string $attribute): ?string
    {
        return $this->paths()[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $key, string $label, ?string $attribute): ?string
    {
        return $this->keys->resolve($key, $label, $this->paths());
    }

    /**
     * Every category's path of default names, by ID; the tree's top is no category
     *
     * @return array<string, string>
     */
    private function paths(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['category' => $this->resource->getTableName(self::CATEGORY_TABLE)],
                ['category.entity_id', 'category.path']
            )
            ->where('category.entity_id <> ?', (int) self::TREE_TOP_ID);
        $name = $this->attributes->join($select, 'category.entity_id', 'name', self::DEFAULT_STORE, 'name');
        $select->columns(['name' => $name]);
        $names = [];
        $chains = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = is_scalar($row['entity_id'] ?? null) ? (string) $row['entity_id'] : '';
            $names[$id] = is_scalar($row['name'] ?? null) ? (string) $row['name'] : '';
            $chains[$id] = explode(self::PATH_SEPARATOR, is_scalar($row['path'] ?? null) ? (string) $row['path'] : '');
        }
        $paths = [];
        foreach ($chains as $id => $chain) {
            $labels = [];
            foreach ($chain as $link) {
                if ($link !== self::TREE_TOP_ID && isset($names[$link])) {
                    $labels[] = $names[$link];
                }
            }
            $paths[(string) $id] = implode(self::LABEL_SEPARATOR, $labels);
        }

        return $paths;
    }
}
