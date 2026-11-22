<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Http\ContextFactory as HttpContextFactory;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreCookieManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

/**
 * The cookies a guest's browser sends to reach a store view's storefront
 *
 * A store view with a base address of its own needs none: its address picks it. When other active store views share
 * the base address, the browser of a guest who chose the store view carries the store cookie and the page cache's
 * vary cookie, computed the way the platform computes it for that guest (the store view against the default store
 * view of its website, the store view's default display currency against that store view's), so the page and its
 * page cache entry are the ones such a guest gets.
 */
class VisitorCookies
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param StoreAddresses $addresses
     * @param HttpContextFactory $contexts
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreAddresses $addresses,
        private readonly HttpContextFactory $contexts
    ) {
    }

    /**
     * The cookies by name; none for a store view whose base address is its own
     *
     * @param int $storeId
     * @return array<string, string>
     */
    public function forStore(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        if (!$store instanceof Store || !$this->sharesBaseAddress($storeId)) {
            return [];
        }
        $website = $this->storeManager->getWebsite($store->getWebsiteId());
        $default = $website instanceof Website ? $website->getDefaultStore() : null;
        $defaultStore = $default instanceof Store ? $default : $store;
        $context = $this->contexts->create();
        $context->setValue(StoreManagerInterface::CONTEXT_STORE, (string) $store->getCode(), $defaultStore->getCode());
        $context->setValue(
            HttpContext::CONTEXT_CURRENCY,
            (string) $store->getDefaultCurrencyCode(),
            (string) $defaultStore->getDefaultCurrencyCode()
        );
        $cookies = [StoreCookieManager::COOKIE_NAME => (string) $store->getCode()];
        $vary = (string) $context->getVaryString();

        return $vary === '' ? $cookies : $cookies + [HttpResponse::COOKIE_VARY_STRING => $vary];
    }

    /**
     * Whether another active store view is reached at the store view's base address
     *
     * @param int $storeId
     * @return bool
     */
    private function sharesBaseAddress(int $storeId): bool
    {
        $base = $this->addresses->page('', $storeId);
        foreach ($this->storeManager->getStores() as $other) {
            $otherId = (int) $other->getId();
            if ($otherId !== $storeId && (bool) $other->getIsActive()
                && $this->addresses->page('', $otherId) === $base
            ) {
                return true;
            }
        }

        return false;
    }
}
