<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Sql;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Magento\Framework\DB\Select;

/**
 * Creates the SQL of a page's values from a correlated select
 */
interface ConditionValueSqlFactoryInterface
{
    /**
     * The values a select finds for one page
     *
     * @param Select $select
     * @param string $valueExpression
     * @return ConditionValueSqlInterface
     */
    public function create(Select $select, string $valueExpression): ConditionValueSqlInterface;
}
