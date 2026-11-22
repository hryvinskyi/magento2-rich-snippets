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
 * A product put together from others (bundle, grouped): the lowest price it can be bought at, as both the final
 * and the regular price, so it is never on sale
 */
class MinimalPriceResolver implements PriceResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(array $row, array $children): ?array
    {
        return $row['min_price'] > 0 ? ['final' => $row['min_price'], 'regular' => $row['min_price']] : null;
    }
}
