<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Attribute;

use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;

/**
 * Creates what a condition reads of a product page for one product attribute
 */
interface ProductAttributeSubjectFactoryInterface
{
    /**
     * The attribute as a condition's subject
     *
     * @param string $code
     * @param string $label
     * @param list<string> $operators the operators' codes, in the builder's order
     * @param string $unit
     * @return ConditionSubjectInterface
     */
    public function create(string $code, string $label, array $operators, string $unit): ConditionSubjectInterface;
}
