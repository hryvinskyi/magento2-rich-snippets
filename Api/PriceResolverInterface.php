<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Finds a product's price for one product type from the platform's price index
 *
 * Registered by product type ID. The amounts are the index's, in the base currency and before tax display: the
 * customer group and website of the index rows are the visitor's, and tax, currency and rounding are applied after.
 *
 * @api
 */
interface PriceResolverInterface
{
    /**
     * The final and the regular price of a product
     *
     * @param array{price:float,final_price:float,min_price:float} $row the product's own index row
     * @param list<array{price:float,final_price:float,min_price:float}> $children the index rows of the
     *     product's children that can be bought (configurable products only; empty for the other types)
     * @return array{final: float, regular: float}|null null when the product has no price
     */
    public function resolve(array $row, array $children): ?array;
}
