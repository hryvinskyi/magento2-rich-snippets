<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeFactoryInterface;

/**
 * Delegates to the generated factories of the condition node contracts
 */
class ConditionTreeFactoryAdapter implements ConditionTreeFactoryInterface
{
    /**
     * @param ConditionTreeInterfaceFactory $trees
     * @param ConditionGroupInterfaceFactory $groups
     * @param ConditionLeafInterfaceFactory $leaves
     */
    public function __construct(
        private readonly ConditionTreeInterfaceFactory $trees,
        private readonly ConditionGroupInterfaceFactory $groups,
        private readonly ConditionLeafInterfaceFactory $leaves
    ) {
    }

    /**
     * @inheritDoc
     */
    public function tree(
        ConditionAggregator $aggregator = ConditionAggregator::All,
        array $children = []
    ): ConditionTreeInterface {
        return $this->trees->create(['aggregator' => $aggregator, 'children' => $children]);
    }

    /**
     * @inheritDoc
     */
    public function group(ConditionAggregator $aggregator, array $children): ConditionGroupInterface
    {
        return $this->groups->create(['aggregator' => $aggregator, 'children' => $children]);
    }

    /**
     * @inheritDoc
     */
    public function leaf(
        string $field,
        ?string $attribute,
        string $operator,
        string|int|float $storedValue,
        ?bool $valueProcessed
    ): ConditionLeafInterface {
        return $this->leaves->create([
            'field' => $field,
            'attribute' => $attribute,
            'operator' => $operator,
            'storedValue' => $storedValue,
            'valueProcessed' => $valueProcessed,
        ]);
    }
}
