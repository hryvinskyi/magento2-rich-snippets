<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One condition: a field of the page (a product attribute when the field is one) compared by an operator with the
 * condition's value
 *
 * The field is a code the condition field pool knows (`product_type`); the operator is one of the operator pool's
 * codes (`==`, `()` …); the value is what the expression builder stores: one text, or several joined by commas for an
 * operator that takes a list, each one the field's stable key (a type id, an attribute set id, a category id, a CMS
 * page identifier), never a label. A condition with no value is incomplete: it is kept and shown, but asked of no
 * page.
 *
 * @api
 */
interface ConditionLeafInterface extends ConditionNodeInterface
{
    /**
     * The condition field's code
     *
     * @return string
     */
    public function getField(): string;

    /**
     * The attribute the field reads, for a field that reads one of many (`color`); null for every other field
     *
     * @return string|null
     */
    public function getAttribute(): ?string;

    /**
     * The operator's code
     *
     * @return string
     */
    public function getOperator(): string;

    /**
     * The value as text; empty for an incomplete condition
     *
     * @return string
     */
    public function getValue(): string;

    /**
     * The value as it was stored: text, or a number the way an earlier save wrote it
     *
     * Kept so the conditions are written back exactly as they were read.
     *
     * @return string|int|float
     */
    public function getStoredValue(): string|int|float;

    /**
     * The `is_value_processed` flag the conditions were stored with, written back unchanged
     *
     * @return bool|null
     */
    public function getValueProcessed(): ?bool;

    /**
     * Whether the condition has a value, so it is asked of the pages it is about
     *
     * @return bool
     */
    public function isComplete(): bool;
}
