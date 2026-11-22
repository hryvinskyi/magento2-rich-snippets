<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Writes a catalog amount the way a store view shows it to the visitor: tax per *Prices in Snippets*, converted
 * from the base currency to the visitor's display currency, rounded to the platform's price precision, as a string
 * with a dot and that many decimals (`"240.00"`)
 *
 * An amount entered including tax is first taken back to its net at the store's origin rate; including tax, the
 * net is charged at the default destination's rate, unless the store keeps gross prices the same in every country.
 */
class PriceDisplay
{
    /**
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxConfig $taxConfig
     * @param TaxRates $taxRates
     * @param PriceTaxSetting $priceTax
     * @param VisitorPriceContext $visitor
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TaxConfig $taxConfig,
        private readonly TaxRates $taxRates,
        private readonly PriceTaxSetting $priceTax,
        private readonly VisitorPriceContext $visitor
    ) {
    }

    /**
     * A product's price as the visitor sees it
     *
     * @param float $amount the catalog amount in the base currency
     * @param int $productTaxClassId
     * @param int $storeId
     * @return string
     */
    public function price(float $amount, int $productTaxClassId, int $storeId): string
    {
        $rates = $this->taxRates->rates($productTaxClassId, $storeId);
        $enteredGross = (bool) $this->taxConfig->priceIncludesTax($storeId);
        $net = $enteredGross ? $amount / (1 + $rates['origin'] / 100) : $amount;
        $value = $net;
        if ($this->priceTax->includesTax($storeId)) {
            $value = $enteredGross && $this->taxConfig->crossBorderTradeEnabled($storeId)
                ? $amount
                : $net * (1 + $rates['destination'] / 100);
        }

        return $this->money($value, $storeId);
    }

    /**
     * An amount without tax, in the visitor's display currency
     *
     * @param float $amount the amount in the base currency
     * @param int $storeId
     * @return string
     */
    public function money(float $amount, int $storeId): string
    {
        $converted = (float) $this->priceCurrency->convertAndRound(
            $amount,
            $storeId,
            $this->visitor->currencyCode($storeId),
            PriceCurrencyInterface::DEFAULT_PRECISION
        );

        return number_format($converted, PriceCurrencyInterface::DEFAULT_PRECISION, '.', '');
    }
}
