<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;

/**
 * Writes conditions in the expression builder's own serialisation
 *
 * The encoding matches the browser's: slashes, non-ASCII characters and line separators are written as they are.
 *
 * @implements ConditionNodeVisitorInterface<array<string, mixed>|null, null>
 */
class ConditionTreePrinter implements ConditionTreePrinterInterface, ConditionNodeVisitorInterface
{
    private const GROUP_VALUE = '1';
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS
        | JSON_THROW_ON_ERROR;

    /**
     * @inheritDoc
     */
    public function print(ConditionTreeInterface $tree): string
    {
        $array = $this->toArray($tree);

        return $array === [] ? '' : json_encode($array, self::JSON_FLAGS);
    }

    /**
     * @inheritDoc
     */
    public function toArray(ConditionTreeInterface $tree): array
    {
        return $tree->accept($this, null) ?? [];
    }

    /**
     * A group in the builder's key order; null when no condition is left in it
     *
     * @param ConditionGroupInterface $group
     * @param mixed $context unused: printing needs nothing besides the node
     * @return array<string, mixed>|null
     */
    public function visitGroup(ConditionGroupInterface $group, mixed $context): ?array
    {
        $children = [];
        foreach ($group->getChildren() as $child) {
            $written = $child->accept($this, null);
            if ($written !== null) {
                $children[] = $written;
            }
        }
        if ($children === []) {
            return null;
        }

        return [
            'type' => ConditionTreeInterface::GROUP_TYPE,
            'attribute' => null,
            'operator' => null,
            'value' => self::GROUP_VALUE,
            'is_value_processed' => null,
            'aggregator' => $group->getAggregator()->value,
            'conditions' => $children,
        ];
    }

    /**
     * A condition in the builder's key order
     *
     * @param ConditionLeafInterface $leaf
     * @param mixed $context unused: printing needs nothing besides the node
     * @return array<string, mixed>
     */
    public function visitLeaf(ConditionLeafInterface $leaf, mixed $context): array
    {
        return [
            'type' => $leaf->getField(),
            'attribute' => $leaf->getAttribute(),
            'operator' => $leaf->getOperator(),
            'value' => $leaf->getStoredValue(),
            'is_value_processed' => $leaf->getValueProcessed(),
        ];
    }
}
