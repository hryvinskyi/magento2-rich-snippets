<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Price;

use Hryvinskyi\RichSnippets\Model\Price\PriceDisplay;
use Hryvinskyi\RichSnippets\Model\Price\PriceTaxSetting;
use Hryvinskyi\RichSnippets\Model\Price\TaxRates;
use Hryvinskyi\RichSnippets\Model\Price\VisitorPriceContext;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

/**
 * A price is written as the visitor sees it: tax per the setting, in the display currency, as a string with two
 * decimals
 */
class PriceDisplayTest extends TestCase
{
    public function testAPriceIsAStringAtThePlatformsPrecision(): void
    {
        self::assertSame('240.00', $this->display(false, false, false)->price(240.0, 2, 1));
        self::assertSame('96.50', $this->display(false, false, false)->price(96.5, 2, 1));
        self::assertSame('0.33', $this->display(false, false, false)->money(1 / 3, 1));
    }

    public function testANetCatalogShownIncludingTaxAddsTheDestinationsTax(): void
    {
        self::assertSame('119.00', $this->display(false, true, false)->price(100.0, 2, 1));
    }

    public function testAGrossCatalogShownExcludingTaxTakesTheOriginsTaxOff(): void
    {
        self::assertSame('100.00', $this->display(true, false, false)->price(121.0, 2, 1));
    }

    public function testAGrossCatalogShownIncludingTaxChargesTheDestinationsRateUnlessGrossPricesAreKept(): void
    {
        self::assertSame('119.00', $this->display(true, true, false)->price(121.0, 2, 1));
        self::assertSame('121.00', $this->display(true, true, true)->price(121.0, 2, 1));
    }

    public function testThePriceIsConvertedToTheVisitorsDisplayCurrency(): void
    {
        $currency = $this->createMock(PriceCurrencyInterface::class);
        $currency->expects(self::once())
            ->method('convertAndRound')
            ->with(100.0, 1, 'EUR', 2)
            ->willReturn(92.0);

        self::assertSame('92.00', $this->display(false, false, false, $currency, 'EUR')->price(100.0, 2, 1));
    }

    /**
     * A display over fixed rates: 19 % at the destination, 21 % at the origin
     *
     * @param bool $enteredGross
     * @param bool $includesTax
     * @param bool $crossBorder
     * @param PriceCurrencyInterface|null $currency
     * @param string $code
     * @return PriceDisplay
     */
    private function display(
        bool $enteredGross,
        bool $includesTax,
        bool $crossBorder,
        ?PriceCurrencyInterface $currency = null,
        string $code = 'USD'
    ): PriceDisplay {
        if ($currency === null) {
            $currency = $this->createMock(PriceCurrencyInterface::class);
            $currency->method('convertAndRound')->willReturnCallback(
                static fn (float $amount): float => round($amount, 2)
            );
        }
        $taxConfig = $this->createMock(TaxConfig::class);
        $taxConfig->method('priceIncludesTax')->willReturn($enteredGross);
        $taxConfig->method('crossBorderTradeEnabled')->willReturn($crossBorder);
        $rates = $this->createMock(TaxRates::class);
        $rates->method('rates')->willReturn(['destination' => 19.0, 'origin' => 21.0]);
        $setting = $this->createMock(PriceTaxSetting::class);
        $setting->method('includesTax')->willReturn($includesTax);
        $visitor = $this->createMock(VisitorPriceContext::class);
        $visitor->method('currencyCode')->willReturn($code);

        return new PriceDisplay($currency, $taxConfig, $rates, $setting, $visitor);
    }
}
