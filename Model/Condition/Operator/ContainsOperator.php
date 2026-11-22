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
 * Holds when one of the page's values holds the condition's text, without regard to case
 *
 * In SQL the text's own `%`, `_` and backslashes are matched as themselves.
 */
class ContainsOperator implements ConditionOperatorInterface
{
    /**
     * The characters LIKE reads as wildcards or as its escape, and the same written as text
     */
    private const LIKE_SPECIALS = ['\\', '%', '_'];
    private const LIKE_ESCAPED = ['\\\\', '\\%', '\\_'];

    /**
     * @param ValueComparison $comparison
     * @param string $code
     * @param string $label in English; translated when it is read
     * @param string $requirement the words after "Set for", %1 the subject, %2 the text; translated when read
     */
    public function __construct(
        private readonly ValueComparison $comparison,
        private readonly string $code = '{}',
        private readonly string $label = 'contains',
        private readonly string $requirement = '%1 containing %2'
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function values(string $value): array
    {
        return $value === '' ? [] : [$value];
    }

    /**
     * @inheritDoc
     */
    public function holds(array $pageValues, array $values): bool
    {
        foreach ($pageValues as $pageValue) {
            if ($this->comparison->contains($pageValue, $values[0])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function predicate(ConditionValueSqlInterface $pageValues, array $values): string
    {
        $pattern = '%' . str_replace(self::LIKE_SPECIALS, self::LIKE_ESCAPED, $values[0]) . '%';

        return $pageValues->exists($pageValues->getValueExpression() . ' LIKE ' . $pageValues->quote($pattern));
    }

    /**
     * @inheritDoc
     */
    public function requirement(string $subject, string $values): string
    {
        return (string) __($this->requirement, $subject, $values);
    }
}
