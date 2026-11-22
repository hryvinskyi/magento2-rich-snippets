<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;

/**
 * Where a condition field's values come from: the options the builder offers, a value's label, an entity's own
 * values and the SQL of those values
 */
interface ConditionValueSourceInterface
{
    /**
     * The options in a store view, in the order the builder lists them
     *
     * Each is `{value, label}`, with `level` and `parent` as well in a tree.
     *
     * @param int $storeId
     * @return list<array<string, string|int>>
     */
    public function options(int $storeId): array;

    /**
     * The label of one value in the store view
     *
     * @param string $key
     * @param int $storeId
     * @return string|null null when there is no such value
     */
    public function label(string $key, int $storeId): ?string;

    /**
     * The keys of the values an entity has, read through the page's data sources
     *
     * @param int $entityId
     * @param int $storeId
     * @return list<string>
     */
    public function entityValues(int $entityId, int $storeId): array;

    /**
     * The values of the entity whose ID an SQL expression gives, as SQL
     *
     * @param string $entityIdExpression
     * @param int $storeId
     * @return ConditionValueSqlInterface
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface;

    /**
     * The variable paths the entity's values are read from, named to the data sources before reading
     *
     * @return list<string>
     */
    public function variablePaths(): array;
}
