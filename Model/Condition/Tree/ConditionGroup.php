<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionNodeInterface;

/**
 * A group of conditions and groups nested in another group, immutable
 */
class ConditionGroup implements ConditionGroupInterface
{
    /**
     * @param ConditionAggregator $aggregator
     * @param list<ConditionNodeInterface> $children
     */
    public function __construct(
        private readonly ConditionAggregator $aggregator,
        private readonly array $children = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function accept(ConditionNodeVisitorInterface $visitor, mixed $context): mixed
    {
        return $visitor->visitGroup($this, $context);
    }

    /**
     * @inheritDoc
     */
    public function getAggregator(): ConditionAggregator
    {
        return $this->aggregator;
    }

    /**
     * @inheritDoc
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @inheritDoc
     */
    public function getLeaves(): array
    {
        $leaves = [];
        foreach ($this->children as $child) {
            array_push($leaves, ...$child->getLeaves());
        }

        return $leaves;
    }

    /**
     * @inheritDoc
     */
    public function getGroupDepth(): int
    {
        $deepest = 0;
        foreach ($this->children as $child) {
            $deepest = max($deepest, $child->getGroupDepth());
        }

        return $deepest + 1;
    }
}
