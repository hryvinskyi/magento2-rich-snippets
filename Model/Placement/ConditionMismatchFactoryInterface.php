<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;

/**
 * Creates the description of a condition a page does not meet
 */
interface ConditionMismatchFactoryInterface
{
    /**
     * A condition with what it asks, its labels and the page's own value
     *
     * @param ConditionLeafInterface $condition
     * @param string $requirement
     * @param string $fieldLabel
     * @param string $operatorLabel
     * @param list<string> $valueLabels
     * @param string|null $pageValue
     * @return ConditionMismatchInterface
     */
    public function create(
        ConditionLeafInterface $condition,
        string $requirement,
        string $fieldLabel,
        string $operatorLabel,
        array $valueLabels,
        ?string $pageValue
    ): ConditionMismatchInterface;
}
