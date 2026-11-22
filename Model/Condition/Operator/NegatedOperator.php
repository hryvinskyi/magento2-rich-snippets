<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Operator;

use Hryvinskyi\RichSnippets\Api\ConditionOperatorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;

/**
 * Holds exactly where another operator does not (`!=` of `==`, `!()` of `()`, `!{}` of `{}`)
 *
 * A page with no value meets the negation, since the operator it negates does not hold there.
 */
class NegatedOperator implements ConditionOperatorInterface
{
    /**
     * @param ConditionOperatorInterface $negated
     * @param string $code
     * @param string $label in English; translated when it is read
     * @param string $requirement the words after "Set for", %1 the subject, %2 the values; translated when read
     */
    public function __construct(
        private readonly ConditionOperatorInterface $negated,
        private readonly string $code,
        private readonly string $label,
        private readonly string $requirement
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __($this->label);
    }

    /**
     * @inheritDoc
     */
    public function takesList(): bool
    {
        return $this->negated->takesList();
    }

    /**
     * @inheritDoc
     */
    public function values(string $value): array
    {
        return $this->negated->values($value);
    }

    /**
     * @inheritDoc
     */
    public function holds(array $pageValues, array $values): bool
    {
        return !$this->negated->holds($pageValues, $values);
    }

    /**
     * @inheritDoc
     */
    public function predicate(ConditionValueSqlInterface $pageValues, array $values): string
    {
        return 'NOT (' . $this->negated->predicate($pageValues, $values) . ')';
    }

    /**
     * @inheritDoc
     */
    public function requirement(string $subject, string $values): string
    {
        return (string) __($this->requirement, $subject, $values);
    }
}
