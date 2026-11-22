<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price\Resolver;

use Hryvinskyi\RichSnippets\Api\PriceResolverInterface;

/**
 * A product sold through its children (configurable): the final price of the cheapest child that can be bought,
 * that child's own price as the regular one; no price when no child can be bought
 */
class LowestChildPriceResolver implements PriceResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(array $row, array $children): ?array
    {
        $cheapest = null;
        foreach ($children as $child) {
            if ($cheapest === null || $child['final_price'] < $cheapest['final_price']) {
                $cheapest = $child;
            }
        }

        return $cheapest === null
            ? null
            : ['final' => $cheapest['final_price'], 'regular' => max($cheapest['price'], $cheapest['final_price'])];
    }
}
