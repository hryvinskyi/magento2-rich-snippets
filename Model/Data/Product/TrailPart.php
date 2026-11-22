<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Breadcrumb\CategoryTrails;
use Hryvinskyi\RichSnippets\Model\Breadcrumb\ProductCategories;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;

/**
 * Each product's default breadcrumb levels: `categories`, the path to its chosen category, and `product`, its own
 * level (name and canonical address)
 */
class TrailPart implements EntityDataPartInterface
{
    private const ROUTE = 'catalog/product/view/id/%d';
    private const REWRITE_TYPE = 'product';
    private const NAME = 'name';

    /**
     * @param ProductCategories $categories
     * @param CategoryTrails $trails
     * @param EavValueReader $reader the product attribute values
     * @param EntityAddresses $addresses
     */
    public function __construct(
        private readonly ProductCategories $categories,
        private readonly CategoryTrails $trails,
        private readonly EavValueReader $reader,
        private readonly EntityAddresses $addresses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $chosen = $this->categories->deepest($ids, $storeId);
        $trails = $this->trails->trails(array_values(array_unique($chosen)), $storeId);
        $urls = $this->addresses->urls(self::REWRITE_TYPE, self::ROUTE, $ids, $storeId);
        $entries = [];
        foreach ($this->reader->values($ids, [self::NAME], $storeId) as $id => $row) {
            $name = $row[self::NAME] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }
            $entries[$id] = [
                'categories' => isset($chosen[$id]) ? ($trails[$chosen[$id]] ?? []) : [],
                'product' => ['name' => $name, 'item' => $urls[$id]],
            ];
        }

        return $entries;
    }
}
