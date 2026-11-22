<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Takes every store view the shop no longer has out of every snippet set for it
 *
 * A store view goes when it is deleted, and with its store group or its website, whose deletion removes it without
 * announcing it on its own; so the snippets' store views are compared with the store views the shop has once the
 * deletion is committed, whichever deletion it was. Each such snippet leaves those store views (see
 * SnippetRootInterface::leaveStoreView) and is saved like any other change, so the pages it was output on and the
 * selection of the store views it changes are cleaned once each save commits. Each snippet is saved on its own: one
 * whose save fails is logged with its code and keeps the deleted store views, and the others still leave them. A
 * snippet set for every store view, or only for store views the shop has, is not saved.
 */
class DeletedStoreViews
{
    /**
     * @param SnippetRootRepositoryInterface $repository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Take the store views the shop no longer has out of every snippet set for one of them
     *
     * @return void
     */
    public function forget(): void
    {
        $this->storeManager->reinitStores();
        $existing = [SnippetInterface::ALL_STORES];
        foreach ($this->storeManager->getStores(true) as $store) {
            $existing[] = (int) $store->getId();
        }
        foreach ($this->repository->list() as $snippet) {
            $gone = array_values(array_diff($snippet->getStoreIds(), $existing));
            if ($gone !== []) {
                $this->leave($snippet, $gone);
            }
        }
    }

    /**
     * Take store views out of one snippet and save it, logging a save that fails
     *
     * @param SnippetRootInterface $snippet
     * @param list<int> $storeIds
     * @return void
     */
    private function leave(SnippetRootInterface $snippet, array $storeIds): void
    {
        try {
            foreach ($storeIds as $storeId) {
                $snippet->leaveStoreView($storeId);
            }
            $this->repository->save($snippet);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'Rich snippets: the snippet "%s" could not leave the deleted store views: %s',
                    $snippet->getCode(),
                    $e->getMessage()
                ),
                ['exception' => $e, 'snippet' => $snippet->getCode()]
            );
        }
    }
}
