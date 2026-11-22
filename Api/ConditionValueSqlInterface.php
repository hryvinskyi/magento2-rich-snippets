<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The values one page has of a condition's subject, as SQL inside the query that lists the pages
 *
 * A page may have no value, one, or several (a product's categories), so an operator writes its test against one
 * value and asks for the pages that have a value passing it. The SQL refers to the page only through the entity ID
 * expression it was made for and joins nothing onto the caller's query, so tests of several conditions combine with
 * AND and OR into one query.
 *
 * @api
 */
interface ConditionValueSqlInterface
{
    /**
     * The SQL expression of one of the page's values, for use inside exists()
     *
     * @return string
     */
    public function getValueExpression(): string;

    /**
     * SQL that holds for a page with at least one value for which the condition holds
     *
     * @param string $condition SQL over getValueExpression()
     * @return string
     */
    public function exists(string $condition): string;

    /**
     * A text quoted as an SQL literal
     *
     * @param string $value
     * @return string
     */
    public function quote(string $value): string;
}
