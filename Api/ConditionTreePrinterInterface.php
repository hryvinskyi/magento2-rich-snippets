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
 * Writes conditions back the way the expression builder writes them
 *
 * Keys come in the builder's order (a group's `type`, `attribute`, `operator`, `value`, `is_value_processed`,
 * `aggregator`, `conditions`; a condition's first five), a stored number and a stored `is_value_processed` come back
 * as they were read, and a group left with no condition is dropped, so a tree the builder wrote is written back byte
 * for byte. No condition at all is the empty text.
 *
 * @api
 */
interface ConditionTreePrinterInterface
{
    /**
     * The tree as the builder's JSON text; empty when it holds no condition
     *
     * @param ConditionTreeInterface $tree
     * @return string
     */
    public function print(ConditionTreeInterface $tree): string;

    /**
     * The tree as the builder's decoded shape, for a document that embeds it; empty when it holds no condition
     *
     * @param ConditionTreeInterface $tree
     * @return array<string,mixed>
     */
    public function toArray(ConditionTreeInterface $tree): array;
}
