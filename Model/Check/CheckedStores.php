<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The store views a catalog check builds snippets for: the active ones whose pages write snippets at all
 */
class CheckedStores
{
    /**
     * @param StoreManagerInterface $stores
     * @param OutputSettings $settings
     */
    public function __construct(
        private readonly StoreManagerInterface $stores,
        private readonly OutputSettings $settings
    ) {
    }

    /**
     * Their IDs, ascending
     *
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->stores->getStores() as $store) {
            $id = (int) $store->getId();
            if ($id > 0 && (int) $store->getIsActive() === 1 && $this->settings->isEnabled($id)) {
                $ids[] = $id;
            }
        }
        sort($ids);

        return $ids;
    }
}
