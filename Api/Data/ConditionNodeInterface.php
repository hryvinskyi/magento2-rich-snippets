<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;

/**
 * One node of the conditions under "Only when": a group of nodes or a single condition
 *
 * Whatever walks the tree (evaluating it on a page, counting pages with it, putting it into words, writing it back)
 * is a visitor: the node calls the visitor's method for its own kind, so a walk never asks a node what it is.
 *
 * @api
 */
interface ConditionNodeInterface
{
    /**
     * Hand the node to the visitor's method for its kind
     *
     * @param ConditionNodeVisitorInterface $visitor
     * @param mixed $context what the visitor needs besides the node (a page, a store view …)
     * @return mixed
     * @template TResult
     * @template TContext
     * @phpstan-param ConditionNodeVisitorInterface<TResult, TContext> $visitor
     * @phpstan-param TContext $context
     * @phpstan-return TResult
     */
    public function accept(ConditionNodeVisitorInterface $visitor, mixed $context): mixed;

    /**
     * The conditions at and below the node, depth first in the order they are written
     *
     * @return list<ConditionLeafInterface>
     */
    public function getLeaves(): array;

    /**
     * How many groups the deepest path from the node down holds, the node included; 0 for a condition
     *
     * @return int
     */
    public function getGroupDepth(): int;
}
