<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Magento\Store\Model\StoreManagerInterface;

/**
 * The catalog scope of a store view: the website its products are assigned to and the root of its category tree
 */
class StoreCatalogScope
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * The ID of the store view's website
     *
     * @param int $storeId
     * @return int
     */
    public function websiteId(int $storeId): int
    {
        return (int) $this->storeManager->getStore($storeId)->getWebsiteId();
    }

    /**
     * The ID of the store view's root category
     *
     * @param int $storeId
     * @return int
     */
    public function rootCategoryId(int $storeId): int
    {
        $store = $this->storeManager->getStore($storeId);

        return (int) $this->storeManager->getGroup((string) $store->getStoreGroupId())->getRootCategoryId();
    }
}
