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
 * Holds when one of the page's values orders against the condition's value the way the operator asks (`>=`, `<` …)
 *
 * One class serves the four orderings: each is configured with its SQL operator and the orderings it accepts (-1
 * below, 0 the same, 1 above). Against a number the page's value is read as a number, in PHP and in SQL alike; a page
 * with no value never holds.
 */
class ComparisonOperator implements ConditionOperatorInterface
{
    private const NUMBER_CAST = 'CAST(%s AS DECIMAL(30,10))';

    /**
     * @var list<int>
     */
    private readonly array $accepted;

    /**
     * @param ValueComparison $comparison
     * @param string $code
     * @param string $label in English; translated when it is read
     * @param string $requirement the words after "Set for", %1 the subject, %2 the value; translated when read
     * @param string $sqlOperator
     * @param array<array-key,int|string> $accepted the orderings the operator holds for; configuration gives them as
     *     numeric text
     */
    public function __construct(
        private readonly ValueComparison $comparison,
        private readonly string $code,
        private readonly string $label,
        private readonly string $requirement,
        private readonly string $sqlOperator,
        array $accepted
    ) {
        $this->accepted = array_values(array_map(static fn (int|string $ordering): int => (int) $ordering, $accepted));
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
            if (in_array($this->comparison->compare($pageValue, $values[0]), $this->accepted, true)) {
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
        $expression = $pageValues->getValueExpression();
        if (is_numeric($values[0])) {
            $expression = sprintf(self::NUMBER_CAST, $expression);
        }

        return $pageValues->exists(
            $expression . ' ' . $this->sqlOperator . ' ' . $pageValues->quote($values[0])
        );
    }

    /**
     * @inheritDoc
     */
    public function requirement(string $subject, string $values): string
    {
        return (string) __($this->requirement, $subject, $values);
    }
}
