<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionNodeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Creates the nodes of a snippet's conditions
 */
interface ConditionTreeFactoryInterface
{
    /**
     * The outermost group; with no arguments, no conditions at all
     *
     * @param ConditionAggregator $aggregator
     * @param list<ConditionNodeInterface> $children
     * @return ConditionTreeInterface
     */
    public function tree(
        ConditionAggregator $aggregator = ConditionAggregator::All,
        array $children = []
    ): ConditionTreeInterface;

    /**
     * A group inside another
     *
     * @param ConditionAggregator $aggregator
     * @param list<ConditionNodeInterface> $children
     * @return ConditionGroupInterface
     */
    public function group(ConditionAggregator $aggregator, array $children): ConditionGroupInterface;

    /**
     * A condition
     *
     * @param string $field
     * @param string|null $attribute
     * @param string $operator
     * @param string|int|float $storedValue
     * @param bool|null $valueProcessed
     * @return ConditionLeafInterface
     * @throws SnippetRuleException when the field or operator is empty or too long, or the attribute is empty
     */
    public function leaf(
        string $field,
        ?string $attribute,
        string $operator,
        string|int|float $storedValue,
        ?bool $valueProcessed
    ): ConditionLeafInterface;
}
