<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;

/**
 * The schema.org types the theme's own markup is checked for: those that describe the entity of a page a snippet
 * describes in its place (Product, ProductGroup)
 *
 * One list for both sides of the check: a block written as one of them is what lets the storefront remove the theme's
 * items, and a page test notes only the theme's items of these types, so an item no snippet replaces (an Organization,
 * a BreadcrumbList) is never taken for the theme's description of the product.
 */
class ThemeMarkupTypes
{
    /**
     * @param array<string,string> $types the schema.org types, without the vocabulary's address, by a key another
     *     module can add to or replace
     */
    public function __construct(private readonly array $types = [])
    {
    }

    /**
     * The types, each once
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_values(array_unique(array_filter($this->types, static fn (string $type): bool => $type !== '')));
    }

    /**
     * The items among these whose type is one of the types, in their order
     *
     * @param list<MicrodataItemInterface> $items
     * @return list<MicrodataItemInterface>
     */
    public function itemsAmong(array $items): array
    {
        $types = $this->all();

        return array_values(array_filter(
            $items,
            static fn (MicrodataItemInterface $item): bool => in_array($item->getType(), $types, true)
        ));
    }
}
