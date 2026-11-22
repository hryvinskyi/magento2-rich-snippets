<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;

/**
 * How many items of each list a set of templates can use at most, so a list is read with that limit
 *
 * A list is limited only when every place that reads it is an `@each` over its plain path with a `@limit` and no
 * `@if` (an item an `@if` drops would leave fewer than the limit); the largest such limit applies. A list read any
 * other way (a filter, an index, an `@each` without a limit) is read whole and left out of the answer.
 *
 * @api
 */
interface CollectionLimitsInterface
{
    /**
     * The limit of each limited list
     *
     * @param list<CompiledTemplateInterface> $templates every template evaluated, the included ones too
     * @return array<string,int> by the list's path
     */
    public function limits(array $templates): array;
}
