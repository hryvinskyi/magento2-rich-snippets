<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;

/**
 * Counts the pages a placement reaches in a store view, kind by kind ("146 of 184 product pages match")
 *
 * The pages are the ones the shop has. A condition is asked only of the pages it is about, the way the storefront
 * selects: a condition about another kind of page, or an incomplete one, is left out of its group. Each kind is
 * counted with one query, the conditions composed into one SQL predicate.
 *
 * @api
 */
interface PlacementCounterInterface
{
    /**
     * The pages of each kind that meet the conditions, of the pages of the kind
     *
     * @param list<string> $kinds
     * @param ConditionTreeInterface $conditions
     * @param int $storeId
     * @return array<string,PlacementCountInterface> by kind, in the order given, each kind once; a kind whose pages
     *     cannot be listed (every other page) is left out
     * @throws \InvalidArgumentException when no kind has one of the codes
     */
    public function count(array $kinds, ConditionTreeInterface $conditions, int $storeId): array;
}
