<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A group of conditions and groups, joined by its aggregator
 *
 * A group with no child it asks of a page is left out of its own parent, the way a condition about another kind of
 * page is: it neither holds nor fails.
 *
 * @api
 */
interface ConditionGroupInterface extends ConditionNodeInterface
{
    /**
     * Whether every asked child must hold, or one is enough
     *
     * @return ConditionAggregator
     */
    public function getAggregator(): ConditionAggregator;

    /**
     * The group's conditions and groups, in the order they are written
     *
     * @return list<ConditionNodeInterface>
     */
    public function getChildren(): array;
}
