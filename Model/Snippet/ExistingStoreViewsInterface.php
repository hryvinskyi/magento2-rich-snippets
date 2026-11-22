<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * The store views the shop has, as the snippet rules ask for them
 */
interface ExistingStoreViewsInterface
{
    /**
     * The store views among the given ones that the shop has, in ascending order
     *
     * Store view 0, every store view, is always among those the shop has. The answer is read when asked, never
     * remembered, so a store view deleted a moment ago is not among them.
     *
     * @param list<int> $storeIds
     * @return list<int>
     */
    public function among(array $storeIds): array;
}
