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

/**
 * Turns conditions into one SQL predicate over the pages of a kind, the same rules as the evaluation of one page
 *
 * A condition not asked of the kind gives nothing and is left out of its group; a group with nothing left gives
 * nothing. The children of "all" are joined by AND, those of "any" by OR, each group in brackets.
 *
 * @phpstan-type Scope array{kind: string, entity: string, store: int}
 * @implements ConditionNodeVisitorInterface<string|null, Scope>
 */
class TreePredicate implements ConditionNodeVisitorInterface
{
    private const NEVER = '1 = 0';

    /**
     * @param AskedLeaves $asked
     */
    public function __construct(private readonly AskedLeaves $asked)
    {
    }

    /**
     * The SQL of a group: its asked children joined by its aggregator, in brackets; null when none is asked
     *
     * @param ConditionGroupInterface $group
     * @param Scope $context
     * @return string|null
     */
    public function visitGroup(ConditionGroupInterface $group, mixed $context): ?string
    {
        $parts = [];
        foreach ($group->getChildren() as $child) {
            $part = $child->accept($this, $context);
            if ($part !== null) {
                $parts[] = $part;
            }
        }
        if ($parts === []) {
            return null;
        }
        $glue = match ($group->getAggregator()) {
            ConditionAggregator::All => ' AND ',
            ConditionAggregator::Any => ' OR ',
        };

        return '(' . implode($glue, $parts) . ')';
    }

    /**
     * The SQL of one condition, in brackets; null when the kind is not asked it
     *
     * @param ConditionLeafInterface $leaf
     * @param Scope $context
     * @return string|null
     */
    public function visitLeaf(ConditionLeafInterface $leaf, mixed $context): ?string
    {
        if (!$this->asked->isAsked($leaf, $context['kind'])) {
            return null;
        }
        $subject = $this->asked->subject($leaf);
        $operator = $this->asked->operator($leaf);
        $values = $this->asked->values($leaf);
        if ($subject === null || $operator === null || $values === []) {
            return '(' . self::NEVER . ')';
        }

        return '(' . $operator->predicate($subject->valueSql($context['entity'], $context['store']), $values) . ')';
    }
}
