<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Hryvinskyi\RichSnippets\Api\Data\PriceTax;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Whether the prices a store view's snippets write include tax, from the *Prices in Snippets* setting
 *
 * "As the catalog shows them" follows the catalog's price display; a catalog that shows prices both ways writes
 * them including tax.
 */
class PriceTaxSetting
{
    private const PATH = 'hryvinskyi_rich_snippets/output/price_tax';

    /**
     * @param ScopeConfigInterface $config
     * @param TaxConfig $taxConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly TaxConfig $taxConfig
    ) {
    }

    /**
     * Whether the store view's snippets write prices including tax
     *
     * @param int $storeId
     * @return bool
     */
    public function includesTax(int $storeId): bool
    {
        $value = $this->config->getValue(self::PATH, ScopeInterface::SCOPE_STORE, $storeId);
        $mode = PriceTax::tryFrom(is_string($value) ? $value : '') ?? PriceTax::Catalog;

        return match ($mode) {
            PriceTax::Including => true,
            PriceTax::Excluding => false,
            PriceTax::Catalog => (int) $this->taxConfig->getPriceDisplayType($storeId)
                !== TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX,
        };
    }
}
