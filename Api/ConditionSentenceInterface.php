<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;

/**
 * Puts conditions into words, as the expression builder's preview reads them: "Product type is not Configurable"
 *
 * Each complete condition is its subject's label, its operator's label and its values' labels joined by ", " (a
 * number followed by its unit); the children of a group are joined by "and" or "or", and every group but the
 * outermost is put in brackets. Incomplete conditions and groups with no complete condition say nothing.
 *
 * @api
 */
interface ConditionSentenceInterface
{
    /**
     * The conditions in words, with the labels of a store view; empty when no condition is complete
     *
     * @param ConditionTreeInterface $tree
     * @param int $storeId
     * @return string
     */
    public function of(ConditionTreeInterface $tree, int $storeId): string;
}
