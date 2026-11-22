<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Whether a store view's storefront shows products that are out of stock, as the inventory setting "Display Out of
 * Stock Products" says: a snippet lists the products the page lists, so it reads the same setting
 */
class StockVisibility
{
    private const SHOW_OUT_OF_STOCK_PATH = 'cataloginventory/options/show_out_of_stock';

    /**
     * @param ScopeConfigInterface $config
     */
    public function __construct(private readonly ScopeConfigInterface $config)
    {
    }

    /**
     * True when the store view's pages list out-of-stock products too
     *
     * @param int $storeId
     * @return bool
     */
    public function showsOutOfStock(int $storeId): bool
    {
        return $this->config->isSetFlag(self::SHOW_OUT_OF_STOCK_PATH, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
