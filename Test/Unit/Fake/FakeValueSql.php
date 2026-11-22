<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;

/**
 * Value SQL that writes itself readably: `EXISTS(<field>(<entity>) <condition>)`, values in single quotes
 */
class FakeValueSql implements ConditionValueSqlInterface
{
    /**
     * @param string $expression the value's expression, as `field(entity)`
     */
    public function __construct(private readonly string $expression)
    {
    }

    public function getValueExpression(): string
    {
        return $this->expression;
    }

    public function exists(string $condition): string
    {
        return 'EXISTS(' . $condition . ')';
    }

    public function quote(string $value): string
    {
        return "'" . $value . "'";
    }
}
