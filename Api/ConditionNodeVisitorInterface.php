<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;

/**
 * A walk over the conditions under "Only when", with one method per kind of node
 *
 * A group's method decides whether and how to walk the group's children; the walk carries a context of its own
 * choosing (the page evaluated, the store view counted in), so one visitor serves every call.
 *
 * @api
 * @template TResult
 * @template TContext
 */
interface ConditionNodeVisitorInterface
{
    /**
     * Visit a group
     *
     * @param ConditionGroupInterface $group
     * @param TContext $context
     * @return TResult
     */
    public function visitGroup(ConditionGroupInterface $group, mixed $context): mixed;

    /**
     * Visit a condition
     *
     * @param ConditionLeafInterface $leaf
     * @param TContext $context
     * @return TResult
     */
    public function visitLeaf(ConditionLeafInterface $leaf, mixed $context): mixed;
}
