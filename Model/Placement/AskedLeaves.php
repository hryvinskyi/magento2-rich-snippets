<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionOperatorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionOperatorPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;

/**
 * Which conditions are asked of the pages of a kind, and what each one reads and compares with
 *
 * A condition is asked only of the pages it is about: one whose field belongs to another kind of page, or one with
 * no value, is not asked at all. A condition the pools no longer resolve (its field, attribute or operator gone) is
 * asked of every kind and never holds, so a snippet never reaches pages through a condition nobody can read.
 */
class AskedLeaves
{
    /**
     * @param ConditionFieldPoolInterface $fields
     * @param ConditionOperatorPoolInterface $operators
     */
    public function __construct(
        private readonly ConditionFieldPoolInterface $fields,
        private readonly ConditionOperatorPoolInterface $operators
    ) {
    }

    /**
     * Whether a condition is asked of the pages of a kind
     *
     * @param ConditionLeafInterface $leaf
     * @param string $kind
     * @return bool
     */
    public function isAsked(ConditionLeafInterface $leaf, string $kind): bool
    {
        if (!$leaf->isComplete()) {
            return false;
        }

        return !$this->fields->has($leaf->getField()) || $this->fields->get($leaf->getField())->getPageKind() === $kind;
    }

    /**
     * What the condition reads; null when it no longer resolves
     *
     * @param ConditionLeafInterface $leaf
     * @return ConditionSubjectInterface|null
     */
    public function subject(ConditionLeafInterface $leaf): ?ConditionSubjectInterface
    {
        return $this->fields->has($leaf->getField())
            ? $this->fields->get($leaf->getField())->subject($leaf->getAttribute())
            : null;
    }

    /**
     * The condition's operator; null when no operator has its code
     *
     * @param ConditionLeafInterface $leaf
     * @return ConditionOperatorInterface|null
     */
    public function operator(ConditionLeafInterface $leaf): ?ConditionOperatorInterface
    {
        return $this->operators->has($leaf->getOperator()) ? $this->operators->get($leaf->getOperator()) : null;
    }

    /**
     * The condition's values, as its operator reads them; the value as one when the operator is gone
     *
     * @param ConditionLeafInterface $leaf
     * @return list<string>
     */
    public function values(ConditionLeafInterface $leaf): array
    {
        return $this->operator($leaf)?->values($leaf->getValue()) ?? [$leaf->getValue()];
    }
}
