<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The operators conditions may use, by code
 *
 * @api
 */
interface ConditionOperatorPoolInterface
{
    /**
     * Whether an operator has the code
     *
     * @param string $code
     * @return bool
     */
    public function has(string $code): bool;

    /**
     * The operator with a code
     *
     * @param string $code
     * @return ConditionOperatorInterface
     * @throws \InvalidArgumentException when no operator has the code
     */
    public function get(string $code): ConditionOperatorInterface;
}
