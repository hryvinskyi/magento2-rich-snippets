<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Sentence;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionGroupInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Model\Placement\AskedLeaves;

/**
 * Puts conditions into words token by token, the way the builder's preview does: a joiner before every child but the
 * first, brackets round every group but the outermost, held to its first and last word, the tokens separated by one
 * space
 *
 * A condition whose field or operator no longer resolves is named by its codes, a value without a label by its key.
 *
 * @phpstan-type Context array{store: int, outermost: bool}
 * @implements ConditionNodeVisitorInterface<list<string>, Context>
 */
class ConditionSentence implements ConditionSentenceInterface, ConditionNodeVisitorInterface
{
    private const TOKEN_SEPARATOR = ' ';
    private const VALUE_SEPARATOR = ', ';

    /**
     * @param AskedLeaves $leaves
     */
    public function __construct(private readonly AskedLeaves $leaves)
    {
    }

    /**
     * @inheritDoc
     */
    public function of(ConditionTreeInterface $tree, int $storeId): string
    {
        return implode(self::TOKEN_SEPARATOR, $tree->accept($this, ['store' => $storeId, 'outermost' => true]));
    }

    /**
     * The tokens of a group: its children's joined by the group's word, in brackets unless it is the outermost
     *
     * @param ConditionGroupInterface $group
     * @param Context $context
     * @return list<string>
     */
    public function visitGroup(ConditionGroupInterface $group, mixed $context): array
    {
        $joiner = (string) match ($group->getAggregator()) {
            ConditionAggregator::All => __('and'),
            ConditionAggregator::Any => __('or'),
        };
        $tokens = [];
        foreach ($group->getChildren() as $child) {
            $part = $child->accept($this, ['store' => $context['store'], 'outermost' => false]);
            if ($part === []) {
                continue;
            }
            if ($tokens !== []) {
                $tokens[] = $joiner;
            }
            array_push($tokens, ...$part);
        }
        if ($tokens === [] || $context['outermost']) {
            return $tokens;
        }
        $first = array_shift($tokens);
        $last = array_pop($tokens);

        return $last === null ? ['(' . $first . ')'] : ['(' . $first, ...$tokens, $last . ')'];
    }

    /**
     * The tokens of a condition: what it reads, its operator and its values; none when it is incomplete
     *
     * @param ConditionLeafInterface $leaf
     * @param Context $context
     * @return list<string>
     */
    public function visitLeaf(ConditionLeafInterface $leaf, mixed $context): array
    {
        if (!$leaf->isComplete()) {
            return [];
        }
        $subject = $this->leaves->subject($leaf);
        $unit = $subject?->getUnit() ?? '';
        $values = array_map(
            static fn (string $value): string => $subject?->valueLabel($value, $context['store'])
                ?? ($unit === '' ? $value : $value . ' ' . $unit),
            $this->leaves->values($leaf)
        );

        return [
            $subject?->getLabel() ?? $leaf->getField(),
            $this->leaves->operator($leaf)?->getLabel() ?? $leaf->getOperator(),
            implode(self::VALUE_SEPARATOR, $values),
        ];
    }
}
