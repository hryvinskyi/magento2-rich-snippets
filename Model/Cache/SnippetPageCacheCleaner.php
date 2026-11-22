<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Cache;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInvalidatorInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Hryvinskyi\RichSnippets\Model\Snippet\PageCacheCleanerInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetReaderInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetWriterInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cleans the cached pages a snippet change reaches, after the change is committed
 *
 * The tags are those of each kind of page in each store view of the snippet's placement before and after the change,
 * for each state in which it is a switched-on page snippet, and the same for every switched-on snippet that
 * includes it, directly or through others. "Every store view" stands for each store view the shop has. Once the
 * transaction commits, the store views whose selection the change can alter are forgotten by the selection index,
 * and the pages carrying the tags are cleaned wherever they are cached. Cleaning after the commit keeps a request
 * that arrives in between from caching the page again with the old snippet.
 */
class SnippetPageCacheCleaner implements PageCacheCleanerInterface
{
    /**
     * @param PageCacheTagInterface $tags
     * @param IncludeGraph $graph
     * @param SnippetReaderInterface $reader
     * @param SnippetWriterInterface $writer
     * @param SelectionIndexInvalidatorInterface $selection
     * @param StoreManagerInterface $storeManager
     * @param PageCacheTagCleaner $pageCache
     */
    public function __construct(
        private readonly PageCacheTagInterface $tags,
        private readonly IncludeGraph $graph,
        private readonly SnippetReaderInterface $reader,
        private readonly SnippetWriterInterface $writer,
        private readonly SelectionIndexInvalidatorInterface $selection,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageCacheTagCleaner $pageCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function cleanAfterCommit(?SnippetInterface $before, ?SnippetInterface $after, array $edges): void
    {
        $snippet = $after ?? $before;
        if ($snippet === null) {
            return;
        }
        $allStores = $this->allStoreIds();
        $own = array_replace($this->placementTags($before, $allStores), $this->placementTags($after, $allStores));
        $tags = $own;
        $includers = $this->reader->byCodes($this->graph->includersOf($snippet->getCode(), $edges));
        foreach ($includers as $includer) {
            $tags = array_replace($tags, $this->placementTags($includer, $allStores));
        }
        if ($tags === []) {
            return;
        }
        $storeIds = array_values(array_unique($own));
        $tagList = array_keys($tags);

        $this->writer->afterCommit(function () use ($storeIds, $tagList): void {
            $this->selection->invalidate($storeIds);
            $this->pageCache->clean($tagList);
        });
    }

    /**
     * The tags of the pages a snippet is output on, each with its store view
     *
     * A snippet that is not a switched-on page snippet is output on no page.
     *
     * @param SnippetInterface|null $snippet
     * @param list<int> $allStores
     * @return array<string, int> store IDs by tag
     */
    private function placementTags(?SnippetInterface $snippet, array $allStores): array
    {
        if ($snippet === null || $snippet->isBlock() || !$snippet->isActive()) {
            return [];
        }
        $storeIds = $snippet->getStoreIds() === [SnippetInterface::ALL_STORES] ? $allStores : $snippet->getStoreIds();
        $tags = [];
        foreach ($snippet->getPageKinds() as $kind) {
            foreach ($storeIds as $storeId) {
                $tags[$this->tags->forKind($kind, $storeId)] = $storeId;
            }
        }

        return $tags;
    }

    /**
     * The IDs of the shop's store views, the admin's excluded
     *
     * @return list<int>
     */
    private function allStoreIds(): array
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }
        sort($storeIds);

        return $storeIds;
    }
}
