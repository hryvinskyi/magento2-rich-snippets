<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Price\PriceDisplay;
use Hryvinskyi\RichSnippets\Model\Price\PriceIndex;
use Hryvinskyi\RichSnippets\Model\Price\PriceResolverPool;

/**
 * Each product's price as the visitor sees it: `final_price` and `regular_price` as strings at the platform's price
 * precision, `on_sale` true when the final price is below the regular one, else null
 *
 * The amounts come from the price index for the visitor's group through the resolver of the product's type; a
 * configurable product's children count only when they can be bought (in stock). A product the index does not hold,
 * or whose resolver finds no price, has no entry.
 */
class PricePart implements EntityDataPartInterface
{
    /**
     * @param PriceIndex $index
     * @param PriceResolverPool $resolvers
     * @param PriceDisplay $display
     */
    public function __construct(
        private readonly PriceIndex $index,
        private readonly PriceResolverPool $resolvers,
        private readonly PriceDisplay $display
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $rows = $this->index->rows($ids, $storeId);
        $children = $this->index->children(array_keys($rows), $storeId);
        $entries = [];
        foreach ($rows as $id => $row) {
            $salable = array_values(array_filter(
                $children[$id] ?? [],
                static fn (array $child): bool => $child['in_stock']
            ));
            $price = $this->resolvers->get($row['type_id'])->resolve($row, $salable);
            if ($price === null) {
                continue;
            }
            $final = $this->display->price($price['final'], $row['tax_class_id'], $storeId);
            $regular = $this->display->price($price['regular'], $row['tax_class_id'], $storeId);
            $entries[$id] = [
                'final_price' => $final,
                'regular_price' => $regular,
                'on_sale' => (float) $final < (float) $regular ? true : null,
            ];
        }

        return $entries;
    }
}
