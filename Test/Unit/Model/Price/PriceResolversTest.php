<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Price;

use Hryvinskyi\RichSnippets\Model\Price\PriceResolverPool;
use Hryvinskyi\RichSnippets\Model\Price\Resolver\FinalPriceResolver;
use Hryvinskyi\RichSnippets\Model\Price\Resolver\LowestChildPriceResolver;
use Hryvinskyi\RichSnippets\Model\Price\Resolver\MinimalPriceResolver;
use PHPUnit\Framework\TestCase;

/**
 * Each product type takes its price from the index in its own way
 */
class PriceResolversTest extends TestCase
{
    public function testAProductSoldAsItIsTakesItsFinalPriceAndItsOwnPriceAsTheRegularOne(): void
    {
        self::assertSame(
            ['final' => 96.0, 'regular' => 120.0],
            (new FinalPriceResolver())->resolve($this->row(120.0, 96.0, 96.0), [])
        );
    }

    public function testAConfigurableProductTakesItsCheapestBuyableChild(): void
    {
        $children = [$this->row(320.0, 310.0, 310.0), $this->row(300.0, 290.0, 290.0), $this->row(350.0, 350.0, 350.0)];

        self::assertSame(
            ['final' => 290.0, 'regular' => 300.0],
            (new LowestChildPriceResolver())->resolve($this->row(0.0, 0.0, 290.0), $children)
        );
        self::assertNull(
            (new LowestChildPriceResolver())->resolve($this->row(0.0, 0.0, 290.0), []),
            'Without a child that can be bought there is no price.'
        );
    }

    public function testABundleOrGroupedProductTakesItsMinimalPriceAndIsNeverOnSale(): void
    {
        self::assertSame(
            ['final' => 45.0, 'regular' => 45.0],
            (new MinimalPriceResolver())->resolve($this->row(0.0, 0.0, 45.0), [])
        );
        self::assertNull((new MinimalPriceResolver())->resolve($this->row(0.0, 0.0, 0.0), []));
    }

    public function testATypeWithoutAResolverOfItsOwnIsPricedByTheFallback(): void
    {
        $pool = new PriceResolverPool(new FinalPriceResolver(), ['configurable' => new LowestChildPriceResolver()]);

        self::assertInstanceOf(LowestChildPriceResolver::class, $pool->get('configurable'));
        self::assertInstanceOf(FinalPriceResolver::class, $pool->get('giftcard'));
    }

    /**
     * An index row
     *
     * @param float $price
     * @param float $final
     * @param float $min
     * @return array{price: float, final_price: float, min_price: float}
     */
    private function row(float $price, float $final, float $min): array
    {
        return ['price' => $price, 'final_price' => $final, 'min_price' => $min];
    }
}
