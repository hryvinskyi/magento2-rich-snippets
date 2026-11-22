<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;

/**
 * The absolute canonical addresses of entities in a store view: their own URL rewrite, else the platform's route to
 * the entity, on the store view's own base address
 */
class EntityAddresses
{
    /**
     * @param CanonicalUrls $canonical
     * @param StoreAddresses $addresses
     */
    public function __construct(
        private readonly CanonicalUrls $canonical,
        private readonly StoreAddresses $addresses
    ) {
    }

    /**
     * The address of each entity
     *
     * @param string $entityType the URL rewrite entity type (`product`, `category`, `cms-page`)
     * @param string $route the platform's route to an entity without a rewrite, `%d` standing for its ID
     * @param list<int> $ids
     * @param int $storeId
     * @return array<int, string> by entity ID
     */
    public function urls(string $entityType, string $route, array $ids, int $storeId): array
    {
        $paths = $this->canonical->paths($entityType, $ids, $storeId);
        $urls = [];
        foreach ($ids as $id) {
            $urls[$id] = $this->addresses->page($paths[$id] ?? sprintf($route, $id), $storeId);
        }

        return $urls;
    }
}
