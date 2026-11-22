<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Magento\Framework\DB\Sql;

/**
 * Declaration of the factory Magento generates for Expression
 * (`setup:di:compile` writes it to `generated/code`), so static analysis runs on an installation that has
 * not been compiled. Loaded through `scanFiles`, which reads the declaration without executing it.
 */
class ExpressionFactory
{
    /**
     * Create an instance
     *
     * @param array<string,mixed> $data constructor arguments by parameter name
     * @return Expression
     */
    public function create(array $data = []): Expression
    {
        throw new \BadMethodCallException('Declaration for static analysis only.');
    }
}
