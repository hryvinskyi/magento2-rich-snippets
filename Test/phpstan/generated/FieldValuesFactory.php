<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Value;

/**
 * Declaration of the factory Magento generates for FieldValues
 * (`setup:di:compile` writes it to `generated/code`), so static analysis runs on an installation that has
 * not been compiled. Loaded through `scanFiles`, which reads the declaration without executing it.
 */
class FieldValuesFactory
{
    /**
     * Create an instance
     *
     * @param array<string,mixed> $data constructor arguments by parameter name
     * @return FieldValues
     */
    public function create(array $data = []): FieldValues
    {
        throw new \BadMethodCallException('Declaration for static analysis only.');
    }
}
