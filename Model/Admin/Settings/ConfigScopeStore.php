<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Settings;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The store view a configuration scope speaks for: the store view itself, a website's default store view, or the
 * shop's default store view at the default scope
 */
class ConfigScopeStore
{
    /**
     * @param StoreManagerInterface $stores
     */
    public function __construct(private readonly StoreManagerInterface $stores)
    {
    }

    /**
     * The store view of the scope the settings page shows
     *
     * @param mixed $website the page's `website` parameter
     * @param mixed $store the page's `store` parameter
     * @return int|null null when the scope names no store view the shop has
     */
    public function storeId(mixed $website, mixed $store): ?int
    {
        try {
            if (is_numeric($store)) {
                return (int) $this->stores->getStore((int) $store)->getId();
            }
            if (is_numeric($website)) {
                $groupId = $this->stores->getWebsite((int) $website)->getDefaultGroupId();

                return (int) $this->stores->getGroup((string) $groupId)->getDefaultStoreId();
            }
            $default = $this->stores->getDefaultStoreView();

            return $default === null ? null : (int) $default->getId();
        } catch (LocalizedException) {
            return null;
        }
    }
}
