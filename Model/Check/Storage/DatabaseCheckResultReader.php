<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Storage;

use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Reads the findings, their pages and the counts of a catalog check from its tables
 *
 * Over every store view a finding's pages are counted from the pages it keeps, each once; a finding whose pages are
 * no longer kept (an older run) counts the sum of its store views'.
 *
 * @phpstan-type Row array{
 *     key: string,
 *     tone: PropertyTone,
 *     rule: string,
 *     path: string,
 *     sources: list<string>,
 *     snippets: list<string>,
 *     pages: int,
 *     kinds: array<string, int>
 * }
 */
class DatabaseCheckResultReader implements CheckResultReaderInterface
{
    private const COUNTED = 'n';

    /**
     * @param CheckTables $tables
     * @param SerializerInterface $json
     * @param FindingInterfaceFactory $findings
     */
    public function __construct(
        private readonly CheckTables $tables,
        private readonly SerializerInterface $json,
        private readonly FindingInterfaceFactory $findings
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findings(int $runId, ?int $storeId): array
    {
        return $this->read($runId, $storeId, null);
    }

    /**
     * @inheritDoc
     */
    public function finding(int $runId, string $key, ?int $storeId): ?FindingInterface
    {
        return $this->read($runId, $storeId, $key)[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function items(int $runId, string $key, ?int $storeId, int $offset, int $limit): array
    {
        $select = $this->itemSelect($runId, $storeId)
            ->reset(Select::COLUMNS)
            ->columns(['kind' => 'item.kind', 'entity_id' => 'item.entity_id', 'store_id' => 'MIN(finding.store_id)'])
            ->where('finding.finding_key = ?', $key)
            ->group(['item.kind', 'item.entity_id'])
            ->order(['item.kind ASC', 'item.entity_id ASC'])
            ->limit($limit, $offset);
        $items = [];
        foreach ($this->tables->connection()->fetchAll($select) as $row) {
            if (!is_array($row) || !is_string($row['kind'] ?? null)) {
                continue;
            }
            $items[] = [
                'kind' => $row['kind'],
                'entity' => $this->number($row['entity_id'] ?? null),
                'store' => $this->number($row['store_id'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function aggregates(int $runId, int $storeId): array
    {
        $connection = $this->tables->connection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->tables->name(CheckTables::AGGREGATE), ['metric', 'subject', 'value'])
                ->where('run_id = ?', $runId)
                ->where('store_id = ?', $storeId)
        );
        $counts = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['metric'] ?? null) && is_string($row['subject'] ?? null)) {
                $counts[$row['metric']][$row['subject']] = $this->number($row['value'] ?? null);
            }
        }

        return $counts;
    }

    /**
     * The findings of a run, of one store view or merged over every store view, optionally of one key
     *
     * @param int $runId
     * @param int|null $storeId
     * @param string|null $key
     * @return list<FindingInterface>
     */
    private function read(int $runId, ?int $storeId, ?string $key): array
    {
        $connection = $this->tables->connection();
        $select = $connection->select()
            ->from($this->tables->name(CheckTables::FINDING))
            ->where('run_id = ?', $runId)
            ->order(['finding_key ASC', 'store_id ASC']);
        if ($storeId !== null) {
            $select->where('store_id = ?', $storeId);
        }
        if ($key !== null) {
            $select->where('finding_key = ?', $key);
        }
        $merged = [];
        foreach ($connection->fetchAll($select) as $stored) {
            $row = is_array($stored) ? $this->row($stored) : null;
            if ($row !== null) {
                $merged[$row['key']] = isset($merged[$row['key']]) ? $this->merge($merged[$row['key']], $row) : $row;
            }
        }
        if ($storeId === null && $merged !== []) {
            $merged = $this->withDistinctPages($runId, $merged);
        }

        return array_values(array_map(
            fn (array $row): FindingInterface => $this->findings->create([
                'key' => $row['key'],
                'tone' => $row['tone'],
                'ruleCode' => $row['rule'],
                'path' => $row['path'],
                'sources' => $row['sources'],
                'snippetCodes' => $row['snippets'],
                'pages' => $row['pages'],
                'pagesByKind' => $row['kinds'],
            ]),
            $merged
        ));
    }

    /**
     * Two store views' rows of one finding as one
     *
     * @param Row $first
     * @param Row $second
     * @return Row
     */
    private function merge(array $first, array $second): array
    {
        $first['snippets'] = array_values(array_unique([...$first['snippets'], ...$second['snippets']]));
        $first['pages'] += $second['pages'];
        foreach ($second['kinds'] as $kind => $pages) {
            $first['kinds'][$kind] = ($first['kinds'][$kind] ?? 0) + $pages;
        }

        return $first;
    }

    /**
     * Merged findings with their pages counted once each, from the pages they keep
     *
     * @param int $runId
     * @param array<string,Row> $merged
     * @return array<string, Row>
     */
    private function withDistinctPages(int $runId, array $merged): array
    {
        $select = $this->itemSelect($runId, null)
            ->reset(Select::COLUMNS)
            ->columns([
                'finding_key' => 'finding.finding_key',
                'kind' => 'item.kind',
                self::COUNTED => 'COUNT(DISTINCT item.entity_id)',
            ])
            ->where('finding.finding_key IN (?)', array_map('strval', array_keys($merged)))
            ->group(['finding.finding_key', 'item.kind']);
        $counted = [];
        foreach ($this->tables->connection()->fetchAll($select) as $row) {
            if (is_array($row) && is_string($row['finding_key'] ?? null) && is_string($row['kind'] ?? null)) {
                $counted[$row['finding_key']][$row['kind']] = $this->number($row[self::COUNTED] ?? null);
            }
        }
        foreach ($counted as $key => $kinds) {
            if (isset($merged[$key])) {
                $merged[$key]['kinds'] = $kinds;
                $merged[$key]['pages'] = array_sum($kinds);
            }
        }

        return $merged;
    }

    /**
     * The pages of a run's findings, joined to their finding
     *
     * @param int $runId
     * @param int|null $storeId
     * @return Select
     */
    private function itemSelect(int $runId, ?int $storeId): Select
    {
        $select = $this->tables->connection()->select()
            ->from(['item' => $this->tables->name(CheckTables::ITEM)], [])
            ->join(
                ['finding' => $this->tables->name(CheckTables::FINDING)],
                'finding.finding_id = item.finding_id',
                []
            )
            ->where('finding.run_id = ?', $runId);
        if ($storeId !== null) {
            $select->where('finding.store_id = ?', $storeId);
        }

        return $select;
    }

    /**
     * A finding's stored row, read; null when it no longer reads as one
     *
     * @param array<mixed> $stored
     * @return Row|null
     */
    private function row(array $stored): ?array
    {
        $tone = PropertyTone::tryFrom(is_string($stored['tone'] ?? null) ? $stored['tone'] : '');
        $key = $stored['finding_key'] ?? null;
        $rule = $stored['rule_code'] ?? null;
        if ($tone === null || !is_string($key) || !is_string($rule)) {
            return null;
        }
        $kinds = [];
        foreach ($this->decoded($stored['pages_by_kind'] ?? null) as $kind => $pages) {
            if (is_numeric($pages)) {
                $kinds[(string) $kind] = (int) $pages;
            }
        }

        return [
            'key' => $key,
            'tone' => $tone,
            'rule' => $rule,
            'path' => is_string($stored['path'] ?? null) ? $stored['path'] : '',
            'sources' => $this->texts($this->decoded($stored['sources'] ?? null)),
            'snippets' => $this->texts($this->decoded($stored['snippets'] ?? null)),
            'pages' => $this->number($stored['pages'] ?? null),
            'kinds' => $kinds,
        ];
    }

    /**
     * A stored JSON array, decoded; empty when it is not one
     *
     * @param mixed $json
     * @return array<mixed>
     */
    private function decoded(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $value = $this->json->unserialize($json);

        return is_array($value) ? $value : [];
    }

    /**
     * The texts of a list
     *
     * @param array<mixed> $values
     * @return list<string>
     */
    private function texts(array $values): array
    {
        return array_values(array_filter($values, 'is_string'));
    }

    /**
     * A stored whole number
     *
     * @param mixed $value
     * @return int
     */
    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
