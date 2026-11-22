<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Names store views as the admin's store-view picker does
 */
class StoreNames
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * A store view's name; its ID when the shop has no such store view
     *
     * @param int $storeId
     * @return string
     */
    public function name(int $storeId): string
    {
        try {
            return (string) $this->storeManager->getStore($storeId)->getName();
        } catch (NoSuchEntityException) {
            return (string) $storeId;
        }
    }
}
