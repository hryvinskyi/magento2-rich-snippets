<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Model\Condition\ConditionValueSourceInterface;

/**
 * A condition value source whose entities' values and value labels are given
 */
class FakeValueSource implements ConditionValueSourceInterface
{
    /**
     * @param string $name the name its SQL expression carries
     * @param array<int, list<string>> $entityValues the values of each entity, by entity ID
     * @param array<array-key, string> $labels the values' labels, by key
     * @param list<string> $paths the variable paths it reads
     */
    public function __construct(
        private readonly string $name,
        private readonly array $entityValues = [],
        private readonly array $labels = [],
        private readonly array $paths = []
    ) {
    }

    public function options(int $storeId): array
    {
        $options = [];
        foreach ($this->labels as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return $options;
    }

    public function label(string $key, int $storeId): ?string
    {
        return $this->labels[$key] ?? null;
    }

    public function entityValues(int $entityId, int $storeId): array
    {
        return $this->entityValues[$entityId] ?? [];
    }

    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        return new FakeValueSql($this->name . '(' . $entityIdExpression . ')');
    }

    public function variablePaths(): array
    {
        return $this->paths;
    }
}
