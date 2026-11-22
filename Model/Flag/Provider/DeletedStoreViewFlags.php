<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Provider;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Warns of a page snippet output nowhere because no store view it is set for exists any more
 *
 * That is a snippet set for no store view, which only the deletion of every store view it was set for leaves, and a
 * snippet whose store views are all unknown to the shop.
 */
class DeletedStoreViewFlags implements SnippetFlagProviderInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param SnippetFlagFactoryInterface $flags
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SnippetFlagFactoryInterface $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function flags(array $snippets, ?int $storeId): array
    {
        $existing = [SnippetInterface::ALL_STORES];
        foreach ($this->storeManager->getStores() as $store) {
            $existing[] = (int) $store->getId();
        }
        $flags = [];
        foreach ($snippets as $snippet) {
            if ($snippet->isBlock() || array_intersect($snippet->getStoreIds(), $existing) !== []) {
                continue;
            }
            $flags[$snippet->getCode()][] = $this->flags->create(
                FlagTone::Warn,
                (string) __(
                    'Every store view it was set for has been deleted, so it is output nowhere. '
                    . 'Pick a store view to output it again.'
                )
            );
        }

        return $flags;
    }
}
