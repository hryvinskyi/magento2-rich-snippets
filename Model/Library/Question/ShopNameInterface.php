<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

/**
 * The shop's name in a store view, as the `store.name` field gives it
 */
interface ShopNameInterface
{
    /**
     * The shop's name; empty when the store view has none
     *
     * @param int $storeId
     * @return string
     */
    public function name(int $storeId): string;
}
