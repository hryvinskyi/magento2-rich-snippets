<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Breadcrumb;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * A product page's levels: the categories down to the one the page's address names, when the product is in it,
 * else down to the product's chosen category; then the product
 */
class ProductTrail implements TrailInterface
{
    /**
     * @param EntityDataSourceInterface $products
     * @param AddressCategory $addressCategory
     * @param ProductCategories $categories
     * @param CategoryTrails $trails
     * @param string $part the product source's part holding each product's default trail
     */
    public function __construct(
        private readonly EntityDataSourceInterface $products,
        private readonly AddressCategory $addressCategory,
        private readonly ProductCategories $categories,
        private readonly CategoryTrails $trails,
        private readonly string $part = 'trail'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function trail(PageEntityInterface $page, int $subjectId): array
    {
        $storeId = $page->getStoreId();
        $entry = $this->products->get($subjectId, $storeId, $this->part);
        if (!is_array($entry) || !is_array($entry['product'] ?? null)) {
            return [];
        }
        $categories = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        $named = $this->addressCategory->categoryFor($subjectId);
        if ($named !== null && $this->categories->isAssigned($subjectId, $named, $storeId)) {
            $categories = $this->trails->trails([$named], $storeId)[$named] ?? $categories;
        }

        return $this->crumbs([...array_values($categories), $entry['product']]);
    }

    /**
     * The well-formed levels among the stored ones
     *
     * @param list<mixed> $levels
     * @return list<array{name: string, item: string}>
     */
    private function crumbs(array $levels): array
    {
        $crumbs = [];
        foreach ($levels as $level) {
            if (is_array($level) && is_string($level['name'] ?? null) && is_string($level['item'] ?? null)) {
                $crumbs[] = ['name' => $level['name'], 'item' => $level['item']];
            }
        }

        return $crumbs;
    }
}
