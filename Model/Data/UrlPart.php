<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;

/**
 * The canonical address of each entity, never one through a category, as `url`
 */
class UrlPart implements EntityDataPartInterface
{
    /**
     * @param EntityAddresses $addresses
     * @param string $entityType the URL rewrite entity type (`product`, `category`)
     * @param string $route the platform's route to an entity without a rewrite, `%d` standing for its ID
     */
    public function __construct(
        private readonly EntityAddresses $addresses,
        private readonly string $entityType,
        private readonly string $route
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $entries = [];
        foreach ($this->addresses->urls($this->entityType, $this->route, $ids, $storeId) as $id => $url) {
            $entries[$id] = ['url' => $url];
        }

        return $entries;
    }
}
