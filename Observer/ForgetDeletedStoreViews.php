<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Observer;

use Hryvinskyi\RichSnippets\Model\Snippet\DeletedStoreViews;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Takes the deleted store views out of the snippets after a store view, a store group or a website is deleted
 *
 * It listens to each deletion once it is committed, so the snippets change only for store views that are really
 * gone; what was deleted does not matter, since the snippets are compared with the store views the shop still has.
 */
class ForgetDeletedStoreViews implements ObserverInterface
{
    /**
     * @param DeletedStoreViews $deletedStoreViews
     */
    public function __construct(private readonly DeletedStoreViews $deletedStoreViews)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $this->deletedStoreViews->forget();
    }
}
