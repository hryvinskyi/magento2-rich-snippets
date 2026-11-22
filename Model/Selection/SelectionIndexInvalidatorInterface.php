<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

/**
 * Drops what the storefront remembers about which snippets each kind of page of a store view gets
 */
interface SelectionIndexInvalidatorInterface
{
    /**
     * Forget the selection of the store views, so the next page of each builds it from the stored snippets
     *
     * @param list<int> $storeIds none forgets nothing
     * @return void
     */
    public function invalidate(array $storeIds): void;
}
