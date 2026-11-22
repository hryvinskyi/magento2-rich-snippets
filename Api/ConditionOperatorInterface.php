<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * How a condition compares the page's values of its subject with the condition's values (`==`, `()`, `>=` …)
 *
 * The same operator decides a page on the storefront and counts pages in SQL, so both agree. Registered in the
 * operator pool under its code, which is the code the expression builder stores; another module adds one there.
 *
 * @api
 */
interface ConditionOperatorInterface
{
    /**
     * The code the conditions store
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The label the builder and the sentences show ("is one of"), translated
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Whether the condition's value is a list (several values joined by commas)
     *
     * @return bool
     */
    public function takesList(): bool;

    /**
     * The values a stored value names: each of a list, or the one text; none for an empty value
     *
     * @param string $value
     * @return list<string>
     */
    public function values(string $value): array;

    /**
     * Whether the condition holds for a page with these values of its subject
     *
     * @param list<string> $pageValues
     * @param list<string> $values the condition's values, at least one
     * @return bool
     */
    public function holds(array $pageValues, array $values): bool;

    /**
     * An SQL predicate that holds for the pages the condition holds for
     *
     * @param ConditionValueSqlInterface $pageValues
     * @param list<string> $values the condition's values, at least one
     * @return string
     */
    public function predicate(ConditionValueSqlInterface $pageValues, array $values): string;

    /**
     * What the condition asks of a page, as the words after "Set for": "product type other than Configurable"
     *
     * @param string $subject the subject's phrase ("product type")
     * @param string $values the values' labels, already joined
     * @return string
     */
    public function requirement(string $subject, string $values): string;
}
