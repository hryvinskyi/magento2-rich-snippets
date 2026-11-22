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
 * Holds when one of the page's values is one of the condition's values: any overlap is enough
 *
 * The condition's value is a list, its values joined by commas as the expression builder stores them.
 */
class OneOfOperator implements ConditionOperatorInterface
{
    private const SEPARATOR = ',';

    /**
     * @param ValueComparison $comparison
     * @param string $code
     * @param string $label in English; translated when it is read
     * @param string $requirement the words after "Set for", %1 the subject, %2 the values; translated when read
     */
    public function __construct(
        private readonly ValueComparison $comparison,
        private readonly string $code = '()',
        private readonly string $label = 'is one of',
        private readonly string $requirement = '%1 %2'
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
        return true;
    }

    /**
     * @inheritDoc
     */
    public function values(string $value): array
    {
        return array_values(array_filter(
            explode(self::SEPARATOR, $value),
            static fn (string $listed): bool => $listed !== ''
        ));
    }

    /**
     * @inheritDoc
     */
    public function holds(array $pageValues, array $values): bool
    {
        foreach ($pageValues as $pageValue) {
            foreach ($values as $value) {
                if ($this->comparison->equals($pageValue, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function predicate(ConditionValueSqlInterface $pageValues, array $values): string
    {
        $quoted = array_map(static fn (string $value): string => $pageValues->quote($value), $values);

        return $pageValues->exists($pageValues->getValueExpression() . ' IN (' . implode(', ', $quoted) . ')');
    }

    /**
     * @inheritDoc
     */
    public function requirement(string $subject, string $values): string
    {
        return (string) __($this->requirement, $subject, $values);
    }
}
