<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The condition fields a snippet's placement may test, by code
 *
 * @api
 */
interface ConditionFieldPoolInterface
{
    /**
     * Whether a condition field has the code
     *
     * @param string $code
     * @return bool
     */
    public function has(string $code): bool;

    /**
     * The condition field with a code
     *
     * @param string $code
     * @return ConditionFieldInterface
     * @throws \InvalidArgumentException when no condition field has the code
     */
    public function get(string $code): ConditionFieldInterface;

    /**
     * The condition fields offered for a kind of page, by code, in their configured order
     *
     * @param string $kind
     * @return array<string,ConditionFieldInterface>
     */
    public function forKind(string $kind): array;

    /**
     * Every field's code, in the configured order
     *
     * @return list<string>
     */
    public function getCodes(): array;
}
