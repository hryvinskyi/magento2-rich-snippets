<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Data\Product\PricePart;
use Hryvinskyi\RichSnippets\Model\Price\PriceDisplay;
use Hryvinskyi\RichSnippets\Model\Price\PriceIndex;
use Hryvinskyi\RichSnippets\Model\Price\PriceResolverPool;
use Hryvinskyi\RichSnippets\Model\Price\Resolver\FinalPriceResolver;
use Hryvinskyi\RichSnippets\Model\Price\Resolver\LowestChildPriceResolver;
use PHPUnit\Framework\TestCase;

/**
 * Prices are strings; "on sale" is true or nothing; a configurable product counts only the children in stock
 *
 * @phpstan-type IndexRow array{
 *     price: float, final_price: float, min_price: float, tax_class_id: int, type_id: string
 * }
 * @phpstan-type ChildRow array{
 *     child_id: int, sku: string, price: float, final_price: float, min_price: float, tax_class_id: int, in_stock: bool
 * }
 */
class PricePartTest extends TestCase
{
    public function testOnSaleIsTrueWhenTheFinalPriceIsBelowTheRegularOneAndNothingOtherwise(): void
    {
        $entries = $this->part(
            [
                1 => $this->row('simple', 240.0, 240.0),
                2 => $this->row('simple', 120.0, 96.0),
            ],
            []
        )->load([1, 2, 3], 1, [], []);

        self::assertSame(['final_price' => '240.00', 'regular_price' => '240.00', 'on_sale' => null], $entries[1]);
        self::assertSame(['final_price' => '96.00', 'regular_price' => '120.00', 'on_sale' => true], $entries[2]);
        self::assertArrayNotHasKey(3, $entries, 'A product the index does not hold has no price.');
    }

    public function testAConfigurableProductIgnoresChildrenOutOfStock(): void
    {
        $entries = $this->part(
            [4 => $this->row('configurable', 0.0, 0.0)],
            [4 => [$this->child(10, 250.0, false), $this->child(11, 310.0, true)]]
        )->load([4], 1, [], []);

        self::assertSame(['final_price' => '310.00', 'regular_price' => '310.00', 'on_sale' => null], $entries[4]);
    }

    /**
     * A part over fixed index rows
     *
     * @param array<int,IndexRow> $rows
     * @param array<int,list<ChildRow>> $children
     * @return PricePart
     */
    private function part(array $rows, array $children): PricePart
    {
        $index = $this->createMock(PriceIndex::class);
        $index->method('rows')->willReturn($rows);
        $index->method('children')->willReturn($children);
        $display = $this->createMock(PriceDisplay::class);
        $display->method('price')->willReturnCallback(
            static fn (float $amount): string => number_format($amount, 2, '.', '')
        );

        return new PricePart(
            $index,
            new PriceResolverPool(new FinalPriceResolver(), ['configurable' => new LowestChildPriceResolver()]),
            $display
        );
    }

    /**
     * An index row
     *
     * @param string $type
     * @param float $price
     * @param float $final
     * @return IndexRow
     */
    private function row(string $type, float $price, float $final): array
    {
        return [
            'price' => $price,
            'final_price' => $final,
            'min_price' => $final,
            'tax_class_id' => 2,
            'type_id' => $type,
        ];
    }

    /**
     * A child's index row
     *
     * @param int $id
     * @param float $price
     * @param bool $inStock
     * @return ChildRow
     */
    private function child(int $id, float $price, bool $inStock): array
    {
        return [
            'child_id' => $id,
            'sku' => 'child-' . $id,
            'price' => $price,
            'final_price' => $price,
            'min_price' => $price,
            'tax_class_id' => 2,
            'in_stock' => $inStock,
        ];
    }
}
