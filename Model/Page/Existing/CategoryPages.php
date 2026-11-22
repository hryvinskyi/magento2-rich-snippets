<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Existing;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\StoreCatalogScope;
use Hryvinskyi\RichSnippets\Model\Page\ExistingPageSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The category pages of a store view: the active categories under its root category, the root itself excluded
 *
 * A root category hangs directly under the tree's top node (ID 1), so its descendants' paths start with
 * `1/<root ID>/`. Being active is the store view's value where it has its own, the default value elsewhere.
 */
class CategoryPages implements ExistingPageSourceInterface
{
    private const CATEGORY_TABLE = 'catalog_category_entity';
    private const TREE_TOP_ID = 1;

    /**
     * @param ResourceConnection $resource
     * @param EavStoreValueJoin $attributes the category attributes
     * @param StoreCatalogScope $scope
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavStoreValueJoin $attributes,
        private readonly StoreCatalogScope $scope
    ) {
    }

    /**
     * @inheritDoc
     */
    public function select(int $storeId): Select
    {
        $select = $this->resource->getConnection()->select()
            ->from(
                ['category' => $this->resource->getTableName(self::CATEGORY_TABLE)],
                [ExistingPagesInterface::ENTITY_ID => 'category.entity_id']
            )
            ->where(
                'category.path LIKE ?',
                sprintf('%d/%d/%%', self::TREE_TOP_ID, $this->scope->rootCategoryId($storeId))
            );
        $active = $this->attributes->join($select, 'category.entity_id', 'is_active', $storeId, 'active');
        $select->where($active . ' = ?', 1);

        return $select;
    }
}
