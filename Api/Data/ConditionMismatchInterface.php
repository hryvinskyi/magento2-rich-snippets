<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One condition asked of a page that does not hold there, with what the page has instead
 *
 * Carries what "Set for product type Configurable; this one is Simple." is written from.
 *
 * @api
 */
interface ConditionMismatchInterface
{
    /**
     * The condition that does not hold
     *
     * @return ConditionLeafInterface
     */
    public function getCondition(): ConditionLeafInterface;

    /**
     * What the condition asks, as the words after "Set for": "product type other than Configurable"
     *
     * @return string
     */
    public function getRequirement(): string;

    /**
     * The label of what the condition reads ("Product type"); the field's code when no field has it
     *
     * @return string
     */
    public function getFieldLabel(): string;

    /**
     * The operator's label ("is not"); its code when no operator has it
     *
     * @return string
     */
    public function getOperatorLabel(): string;

    /**
     * The labels of the condition's values, a value's key where it has no label
     *
     * @return list<string>
     */
    public function getValueLabels(): array;

    /**
     * The labels of the page's own values, joined by ", "
     *
     * Null when the page has none, or when the page's value is the page itself.
     *
     * @return string|null
     */
    public function getPageValue(): ?string;
}
