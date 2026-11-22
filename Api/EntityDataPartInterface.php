<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Loads one part of the data of an entity source (the prices, the gallery …) for many entities at once
 *
 * A part is registered in its source's pool under its code. It reads with a fixed number of queries, whatever the
 * number of entities, and keeps nothing between calls: the source keeps what it returns.
 *
 * @api
 */
interface EntityDataPartInterface
{
    /**
     * The part's data for each entity
     *
     * @param list<int> $ids
     * @param int $storeId
     * @param list<string> $variablePaths the variable paths the templates read, so a part loads only what they need
     * @param array<string,int> $collectionLimits the most items any template takes from a list, by the list's path
     * @return array<int,mixed> by entity ID; an entity the part holds nothing for is left out
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array;
}
