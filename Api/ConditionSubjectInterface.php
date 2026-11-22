<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * What one condition reads of a page: a condition field, or the product attribute a field reads
 *
 * It supplies the page's values and the SQL of those values; the condition's operator does the comparing, the same
 * way for every subject.
 *
 * @api
 */
interface ConditionSubjectInterface
{
    /**
     * The label the sentences show ("Product type", or an attribute's label), translated
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The words naming the subject inside a sentence ("product type", "the CMS page"), translated
     *
     * A phrase of its own, not the label lower-cased, so every language words it correctly.
     *
     * @return string
     */
    public function getPhrase(): string;

    /**
     * The codes of the operators the subject offers, in the order the builder lists them
     *
     * @return list<string>
     */
    public function getOperators(): array;

    /**
     * What a number of the subject is entered in (a currency or weight code); empty when nothing is said
     *
     * @return string
     */
    public function getUnit(): string;

    /**
     * Whether an explanation names the page's own value ("this one is Simple")
     *
     * False for a subject whose value is the page itself, which the explanation already names.
     *
     * @return bool
     */
    public function namesOwnValue(): bool;

    /**
     * The page's values, by their stable keys; none for a page of another kind or one that shows no entity
     *
     * @param PageEntityInterface $page
     * @return list<string>
     */
    public function pageValues(PageEntityInterface $page): array;

    /**
     * The label of a value in a store view
     *
     * @param string $value the value's stable key
     * @param int $storeId
     * @return string|null null when the subject has no such value
     */
    public function valueLabel(string $value, int $storeId): ?string;

    /**
     * The page's values as SQL in a query listing pages
     *
     * @param string $entityIdExpression the SQL expression of the page's entity ID in that query
     * @param int $storeId the store view the pages are counted in
     * @return ConditionValueSqlInterface
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface;

    /**
     * The variable paths the page's values are read from, named to the data sources before a page is evaluated
     *
     * @return list<string>
     */
    public function getVariablePaths(): array;
}
