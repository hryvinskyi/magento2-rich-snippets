<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Cache;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Indexer\CacheContextFactory;

/**
 * Cleans the cached pages that carry any of some tags, wherever they are cached
 *
 * The platform's clean-by-tags event is dispatched with a cache context carrying the tags: the built-in page cache
 * and a caching proxy in front of the shop both clean by it. Call it once what changed is committed, so a request
 * in between cannot cache a page again from the old state.
 */
class PageCacheTagCleaner
{
    private const CLEAN_BY_TAGS_EVENT = 'clean_cache_by_tags';

    /**
     * @param EventManagerInterface $events
     * @param CacheContextFactory $cacheContextFactory
     */
    public function __construct(
        private readonly EventManagerInterface $events,
        private readonly CacheContextFactory $cacheContextFactory
    ) {
    }

    /**
     * Clean the pages carrying any of the tags; no tag cleans nothing
     *
     * @param list<string> $tags
     * @return void
     */
    public function clean(array $tags): void
    {
        if ($tags === []) {
            return;
        }
        $context = $this->cacheContextFactory->create();
        $context->registerTags($tags);
        $this->events->dispatch(self::CLEAN_BY_TAGS_EVENT, ['object' => $context]);
    }
}
