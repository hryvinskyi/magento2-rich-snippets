<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Operator;

/**
 * Compares a page's value with a condition's value the way the database compares them, so a page decided on the
 * storefront and a page counted in SQL agree
 *
 * Text compares without regard to case, as the catalog's collations do. Two numbers compare as numbers, so a stored
 * "240.000000" is 240. Ordering against a number reads the page's value as a number, its leading digits or 0 the way
 * a cast does; ordering against any other text compares the texts, which orders ISO dates correctly.
 */
class ValueComparison
{
    /**
     * Whether two values are the same
     *
     * @param string $pageValue
     * @param string $value
     * @return bool
     */
    public function equals(string $pageValue, string $value): bool
    {
        if (is_numeric($pageValue) && is_numeric($value)) {
            return (float) $pageValue === (float) $value;
        }

        return mb_strtolower($pageValue, 'UTF-8') === mb_strtolower($value, 'UTF-8');
    }

    /**
     * How the page's value orders against the condition's value: -1 below, 0 the same, 1 above
     *
     * @param string $pageValue
     * @param string $value
     * @return int
     */
    public function compare(string $pageValue, string $value): int
    {
        if (is_numeric($value)) {
            return (float) $pageValue <=> (float) $value;
        }

        return strcmp(mb_strtolower($pageValue, 'UTF-8'), mb_strtolower($value, 'UTF-8')) <=> 0;
    }

    /**
     * Whether a value holds another, without regard to case
     *
     * @param string $pageValue
     * @param string $value
     * @return bool
     */
    public function contains(string $pageValue, string $value): bool
    {
        return mb_stripos($pageValue, $value, 0, 'UTF-8') !== false;
    }
}
