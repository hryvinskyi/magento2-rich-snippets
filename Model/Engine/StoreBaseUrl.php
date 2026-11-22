<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\BaseUrlInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The `url` filter's site address: the current store view's storefront address
 */
class StoreBaseUrl implements BaseUrlInterface
{
    /**
     * @param StoreAddresses $addresses
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreAddresses $addresses,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function absolute(string $url): string
    {
        return $this->addresses->page($url, (int) $this->storeManager->getStore()->getId());
    }
}
