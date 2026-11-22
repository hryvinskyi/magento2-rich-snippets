<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Who the prices are for: the customer group and the display currency the page cache varies the page on
 *
 * Both come from the request's HTTP context, which is what the full-page cache keys a page by, so a snippet never
 * shows a price other than the one its cached page was built for. Nothing here reads the customer session, the
 * quote or the visitor's address. Without a group the visitor is a guest; a currency the store view does not offer
 * gives way to its default display currency.
 */
class VisitorPriceContext
{
    /**
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The visitor's customer group
     *
     * @return int
     */
    public function customerGroupId(): int
    {
        $group = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        return is_numeric($group) ? (int) $group : GroupInterface::NOT_LOGGED_IN_ID;
    }

    /**
     * The currency the visitor sees prices of a store view in
     *
     * @param int $storeId
     * @return string
     */
    public function currencyCode(int $storeId): string
    {
        $store = $this->storeManager->getStore($storeId);
        if (!$store instanceof Store) {
            throw new \LogicException(sprintf('Store view %d has no currencies.', $storeId));
        }
        $default = (string) $store->getDefaultCurrencyCode();
        $current = $this->httpContext->getValue(HttpContext::CONTEXT_CURRENCY);
        if (!is_string($current) || $current === '' || $current === $default) {
            return $default;
        }
        $offered = $store->getAvailableCurrencyCodes(true);

        return in_array($current, $offered, true) ? $current : $default;
    }
}
