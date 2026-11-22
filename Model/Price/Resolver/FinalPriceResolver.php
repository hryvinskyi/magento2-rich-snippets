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
 * A product sold as it is (simple, virtual, downloadable): its own final price, its own price as the regular one
 */
class FinalPriceResolver implements PriceResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(array $row, array $children): ?array
    {
        return ['final' => $row['final_price'], 'regular' => max($row['price'], $row['final_price'])];
    }
}
