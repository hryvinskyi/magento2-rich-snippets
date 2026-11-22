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
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The product pages of a store view: enabled products visible in the catalog, in search or both, assigned to the
 * store view's website
 *
 * Status and visibility are the store view's values where it has its own, the default values elsewhere.
 */
class ProductPages implements ExistingPageSourceInterface
{
    private const PRODUCT_TABLE = 'catalog_product_entity';
    private const WEBSITE_TABLE = 'catalog_product_website';

    /**
     * @param ResourceConnection $resource
     * @param EavStoreValueJoin $attributes the product attributes
     * @param StoreCatalogScope $scope
     * @param Visibility $visibility
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavStoreValueJoin $attributes,
        private readonly StoreCatalogScope $scope,
        private readonly Visibility $visibility
    ) {
    }

    /**
     * @inheritDoc
     */
    public function select(int $storeId): Select
    {
        $select = $this->resource->getConnection()->select()
            ->from(
                ['product' => $this->resource->getTableName(self::PRODUCT_TABLE)],
                [ExistingPagesInterface::ENTITY_ID => 'product.entity_id']
            )
            ->join(
                ['website' => $this->resource->getTableName(self::WEBSITE_TABLE)],
                sprintf(
                    'website.product_id = product.entity_id AND website.website_id = %d',
                    $this->scope->websiteId($storeId)
                ),
                []
            );
        $status = $this->attributes->join($select, 'product.entity_id', 'status', $storeId, 'status');
        $visibility = $this->attributes->join($select, 'product.entity_id', 'visibility', $storeId, 'visibility');
        $select->where($status . ' = ?', Status::STATUS_ENABLED)
            ->where($visibility . ' IN (?)', $this->visibility->getVisibleInSiteIds());

        return $select;
    }
}
