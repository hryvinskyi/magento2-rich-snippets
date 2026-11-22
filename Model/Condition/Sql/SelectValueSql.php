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
 * A page's values as a correlated select: the select finds the rows holding one page's values, and a condition on a
 * value is added to it inside EXISTS
 *
 * The condition is appended to the select's SQL as text rather than through the select's where(), which would
 * replace every `?` in it, those inside a quoted value included. That is why the select may carry no grouping,
 * ordering or limit, which would follow its WHERE clause. The select it is given is never changed, so one value SQL
 * serves every condition on the same subject.
 */
class SelectValueSql implements ConditionValueSqlInterface
{
    /**
     * @param Select $select the rows of one page's values, correlated to the page through its entity ID expression
     * @param string $valueExpression the SQL expression of one value in those rows
     */
    public function __construct(
        private readonly Select $select,
        private readonly string $valueExpression
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getValueExpression(): string
    {
        return $this->valueExpression;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $condition): string
    {
        if ($this->select->getPart(Select::GROUP) !== [] || $this->select->getPart(Select::ORDER) !== []
            || $this->select->getPart(Select::LIMIT_COUNT) !== null
        ) {
            throw new \LogicException('A value select carries no grouping, ordering or limit.');
        }
        $glue = $this->select->getPart(Select::WHERE) === [] ? ' WHERE ' : ' AND ';

        return 'EXISTS (' . $this->select->assemble() . $glue . '(' . $condition . '))';
    }

    /**
     * @inheritDoc
     */
    public function quote(string $value): string
    {
        return $this->select->getConnection()->quoteInto('?', $value);
    }
}
