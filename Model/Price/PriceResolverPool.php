<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Hryvinskyi\RichSnippets\Api\PriceResolverInterface;

/**
 * The price resolvers, by product type ID; a type without one of its own is priced by the fallback
 */
class PriceResolverPool
{
    /**
     * @param PriceResolverInterface $fallback
     * @param array<string,PriceResolverInterface> $resolvers by product type ID
     */
    public function __construct(
        private readonly PriceResolverInterface $fallback,
        private readonly array $resolvers = []
    ) {
    }

    /**
     * The resolver of a product type
     *
     * @param string $typeId
     * @return PriceResolverInterface
     */
    public function get(string $typeId): PriceResolverInterface
    {
        return $this->resolvers[$typeId] ?? $this->fallback;
    }
}
