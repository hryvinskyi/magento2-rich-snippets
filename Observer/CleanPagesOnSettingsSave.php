<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Observer;

use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Model\Cache\PageCacheTagCleaner;
use Hryvinskyi\RichSnippets\Model\Config\SavedScopeStores;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Cleans the cached pages of every store view a save of the snippets' settings reaches
 *
 * Any of the section's settings (switching the snippets off, their position or format, how prices are written,
 * removing the theme's markup) changes what those pages write, and every storefront page carries its store view's
 * tag. The platform announces the save once the section's values are committed, so no request in between caches a
 * page again from the old settings.
 */
class CleanPagesOnSettingsSave implements ObserverInterface
{
    /**
     * @param SavedScopeStores $scopeStores
     * @param PageCacheTagInterface $tags
     * @param PageCacheTagCleaner $pageCache
     */
    public function __construct(
        private readonly SavedScopeStores $scopeStores,
        private readonly PageCacheTagInterface $tags,
        private readonly PageCacheTagCleaner $pageCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $storeIds = $this->scopeStores->storeIds(
            $this->text($event->getData('website')),
            $this->text($event->getData('store'))
        );
        $tags = [];
        foreach ($storeIds as $storeId) {
            $tags[] = $this->tags->forStore($storeId);
        }
        $this->pageCache->clean($tags);
    }

    /**
     * A scope value of the event as text; empty when the scope was not saved
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
