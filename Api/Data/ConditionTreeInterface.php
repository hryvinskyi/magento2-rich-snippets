<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The conditions under "Only when": the outermost group of the tree the expression builder writes
 *
 * A page meets the conditions unless the tree evaluates to false on it: a condition about another kind of page, or
 * an incomplete one, is not asked and is left out of its group, a group with nothing asked is left out of its parent,
 * and a tree with nothing asked holds. No conditions at all is a tree whose group has no children.
 *
 * @api
 */
interface ConditionTreeInterface extends ConditionGroupInterface
{
    /**
     * The condition type a group is stored under; a condition is stored under its field's code
     */
    public const GROUP_TYPE = 'rich_snippets/combine';

    /**
     * Whether the tree holds no condition at all, complete or not
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Whether a group sits inside the outermost one, even an empty group
     *
     * @return bool
     */
    public function hasNestedGroup(): bool;

    /**
     * The conditions that have a value, depth first in the order they are written
     *
     * @return list<ConditionLeafInterface>
     */
    public function getCompleteLeaves(): array;
}
