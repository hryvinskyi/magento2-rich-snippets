<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;

/**
 * Names each page's entity to the source of its kind of page and each page's store view to the store source
 *
 * A kind of page with no source of its own (every other page) names only its store view.
 */
class PageDataPrefetch implements PageDataPrefetchInterface
{
    /**
     * @param EntityDataSourceInterface $storeSource the source whose entities are store views
     * @param array<string,EntityDataSourceInterface> $kindSources the source of each kind of page's entity, by kind
     */
    public function __construct(
        private readonly EntityDataSourceInterface $storeSource,
        private readonly array $kindSources = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function prefetch(array $pages, array $variablePaths, array $collectionLimits = []): void
    {
        $entities = [];
        $stores = [];
        foreach ($pages as $page) {
            $storeId = $page->getStoreId();
            $stores[$storeId] = true;
            $entityId = $page->getEntityId();
            if ($entityId !== null && isset($this->kindSources[$page->getKind()])) {
                $entities[$page->getKind()][$storeId][$entityId] = true;
            }
        }
        foreach ($entities as $kind => $byStore) {
            foreach ($byStore as $storeId => $ids) {
                $this->kindSources[$kind]->prefetch(array_keys($ids), $storeId, $variablePaths, $collectionLimits);
            }
        }
        foreach (array_keys($stores) as $storeId) {
            $this->storeSource->prefetch([$storeId], $storeId, $variablePaths, $collectionLimits);
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->storeSource->reset();
        foreach ($this->kindSources as $source) {
            $source->reset();
        }
    }
}
