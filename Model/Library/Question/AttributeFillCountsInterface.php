<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

/**
 * How many of a store view's product pages fill a product attribute
 *
 * Product pages are the pages that exist (ExistingPagesInterface); an attribute is filled for a product when its
 * value in the store view, or the default value it falls back to, is neither null nor empty.
 */
interface AttributeFillCountsInterface
{
    /**
     * The number of product pages whose product fills the attribute
     *
     * @param string $attributeCode
     * @param int $storeId
     * @return int
     * @throws \LogicException when the attribute does not exist or is stored in the product table
     */
    public function filled(string $attributeCode, int $storeId): int;

    /**
     * The number of product pages
     *
     * @param int $storeId
     * @return int
     */
    public function total(int $storeId): int;
}
