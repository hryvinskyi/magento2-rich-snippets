<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * Evaluates conditions on one page: true or false for what is asked, null for what is not
 *
 * A condition not asked of the page's kind is null; a group's null children are left out, and a group left with
 * nothing is null itself. "All" needs every remaining child true, "any" one.
 *
 * @implements ConditionNodeVisitorInterface<bool|null, PageEntityInterface>
 */
class TreeEvaluation implements ConditionNodeVisitorInterface
{
    /**
     * @param AskedLeaves $asked
     */
    public function __construct(private readonly AskedLeaves $asked)
    {
    }

    /**
     * The verdict of a group: its asked children combined by its aggregator; null when none is asked
     *
     * @param ConditionGroupInterface $group
     * @param PageEntityInterface $context
     * @return bool|null
     */
    public function visitGroup(ConditionGroupInterface $group, mixed $context): ?bool
    {
        $results = [];
        foreach ($group->getChildren() as $child) {
            $result = $child->accept($this, $context);
            if ($result !== null) {
                $results[] = $result;
            }
        }
        if ($results === []) {
            return null;
        }

        return match ($group->getAggregator()) {
            ConditionAggregator::All => !in_array(false, $results, true),
            ConditionAggregator::Any => in_array(true, $results, true),
        };
    }

    /**
     * The verdict of one condition; null when the page's kind is not asked it
     *
     * @param ConditionLeafInterface $leaf
     * @param PageEntityInterface $context
     * @return bool|null
     */
    public function visitLeaf(ConditionLeafInterface $leaf, mixed $context): ?bool
    {
        if (!$this->asked->isAsked($leaf, $context->getKind())) {
            return null;
        }
        $subject = $this->asked->subject($leaf);
        $operator = $this->asked->operator($leaf);
        $values = $this->asked->values($leaf);
        if ($subject === null || $operator === null || $values === []) {
            return false;
        }

        return $operator->holds($subject->pageValues($context), $values);
    }
}
