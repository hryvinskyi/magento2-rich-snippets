<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\ConditionNodeVisitorInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;

/**
 * One condition, immutable; refused when its field or operator is empty or too long, or its attribute is empty
 *
 * Whether the field exists, reads the attribute and offers the operator is the parser's to check against the pools:
 * a condition on its own knows no vocabulary.
 */
class ConditionLeaf implements ConditionLeafInterface
{
    private const NAME_MAX_LENGTH = 255;

    /**
     * @param string $field
     * @param string|null $attribute
     * @param string $operator
     * @param string|int|float $storedValue
     * @param bool|null $valueProcessed
     * @throws SnippetRuleException
     */
    public function __construct(
        private readonly string $field,
        private readonly ?string $attribute,
        private readonly string $operator,
        private readonly string|int|float $storedValue = '',
        private readonly ?bool $valueProcessed = null
    ) {
        foreach ([$field, $operator, $attribute ?? 'none'] as $name) {
            if ($name === '' || mb_strlen($name, 'UTF-8') > self::NAME_MAX_LENGTH) {
                throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function accept(ConditionNodeVisitorInterface $visitor, mixed $context): mixed
    {
        return $visitor->visitLeaf($this, $context);
    }

    /**
     * @inheritDoc
     */
    public function getLeaves(): array
    {
        return [$this];
    }

    /**
     * @inheritDoc
     */
    public function getGroupDepth(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(): ?string
    {
        return $this->attribute;
    }

    /**
     * @inheritDoc
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @inheritDoc
     */
    public function getValue(): string
    {
        return (string) $this->storedValue;
    }

    /**
     * @inheritDoc
     */
    public function getStoredValue(): string|int|float
    {
        return $this->storedValue;
    }

    /**
     * @inheritDoc
     */
    public function getValueProcessed(): ?bool
    {
        return $this->valueProcessed;
    }

    /**
     * @inheritDoc
     */
    public function isComplete(): bool
    {
        return $this->getValue() !== '';
    }
}
