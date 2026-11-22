<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionGroup;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionLeaf;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTree;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeFactoryInterface;

/**
 * Builds the module's own tree nodes, as the generated factories do in the shop
 */
class TreeFactoryFake implements ConditionTreeFactoryInterface
{
    public function tree(
        ConditionAggregator $aggregator = ConditionAggregator::All,
        array $children = []
    ): ConditionTreeInterface {
        return new ConditionTree($aggregator, $children);
    }

    public function group(ConditionAggregator $aggregator, array $children): ConditionGroupInterface
    {
        return new ConditionGroup($aggregator, $children);
    }

    public function leaf(
        string $field,
        ?string $attribute,
        string $operator,
        string|int|float $storedValue,
        ?bool $valueProcessed
    ): ConditionLeafInterface {
        return new ConditionLeaf($field, $attribute, $operator, $storedValue, $valueProcessed);
    }
}
