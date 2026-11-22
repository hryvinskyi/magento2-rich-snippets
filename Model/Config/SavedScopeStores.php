<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

use Magento\Store\Model\StoreManagerInterface;

/**
 * The store views a settings save reaches: the saved store view, every store view of the saved website, or every
 * store view of the shop when the default scope is saved
 */
class SavedScopeStores
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * The IDs of the store views of a saved scope, ascending
     *
     * @param string $website the saved website's ID; empty when no website was saved
     * @param string $store the saved store view's ID; empty when no store view was saved
     * @return list<int>
     */
    public function storeIds(string $website, string $store): array
    {
        if ($store !== '') {
            return [(int) $this->storeManager->getStore($store)->getId()];
        }
        $websiteId = $website !== '' ? (int) $this->storeManager->getWebsite($website)->getId() : null;
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $scopeStore) {
            if ($websiteId === null || (int) $scopeStore->getWebsiteId() === $websiteId) {
                $storeIds[] = (int) $scopeStore->getId();
            }
        }
        sort($storeIds);

        return $storeIds;
    }
}
