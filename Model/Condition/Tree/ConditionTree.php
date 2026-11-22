<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionNodeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;

/**
 * The outermost group of a snippet's conditions, immutable; no children is no conditions
 */
class ConditionTree extends ConditionGroup implements ConditionTreeInterface
{
    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return $this->getLeaves() === [];
    }

    /**
     * @inheritDoc
     */
    public function hasNestedGroup(): bool
    {
        foreach ($this->getChildren() as $child) {
            if ($this->isGroup($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getCompleteLeaves(): array
    {
        return array_values(array_filter(
            $this->getLeaves(),
            static fn (ConditionLeafInterface $leaf): bool => $leaf->isComplete()
        ));
    }

    /**
     * Whether a child is a group: a group counts itself in its depth, a condition does not
     *
     * @param ConditionNodeInterface $child
     * @return bool
     */
    private function isGroup(ConditionNodeInterface $child): bool
    {
        return $child->getGroupDepth() > 0;
    }
}
