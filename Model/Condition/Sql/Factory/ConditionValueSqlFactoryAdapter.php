<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Sql\Factory;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Condition\Sql\ConditionValueSqlFactoryInterface;
use Magento\Framework\DB\Select;

/**
 * Delegates to the generated factory of the value SQL contract
 */
class ConditionValueSqlFactoryAdapter implements ConditionValueSqlFactoryInterface
{
    /**
     * @param ConditionValueSqlInterfaceFactory $generated
     */
    public function __construct(private readonly ConditionValueSqlInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(Select $select, string $valueExpression): ConditionValueSqlInterface
    {
        return $this->generated->create(['select' => $select, 'valueExpression' => $valueExpression]);
    }
}
