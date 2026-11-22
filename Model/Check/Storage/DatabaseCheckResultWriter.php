<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Storage;

use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultWriterInterface;
use Hryvinskyi\RichSnippets\Model\Check\StoreTally;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Writes the findings, their pages and the counts of a catalog check into its tables
 *
 * A batch of pages costs the same few statements whatever its size: one that writes every finding of the store
 * view as it stands, one that reads their IDs and one that adds the batch's pages (a page a finding already has is
 * left as it is).
 *
 * @phpstan-import-type Item from StoreTally
 */
class DatabaseCheckResultWriter implements CheckResultWriterInterface
{
    private const COUNTED = 'n';

    /**
     * @param CheckTables $tables
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly CheckTables $tables,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function writeFindings(int $runId, StoreTally $tally): void
    {
        $findings = $tally->findings();
        $items = $tally->takeItems();
        if ($findings === []) {
            return;
        }
        $storeId = $tally->getStoreId();
        $rows = [];
        foreach ($findings as $key => $finding) {
            $rows[] = [
                'run_id' => $runId,
                'store_id' => $storeId,
                'finding_key' => (string) $key,
                'rule_code' => $finding['rule'],
                'path' => $finding['path'],
                'sources' => (string) $this->json->serialize($finding['sources']),
                'tone' => $finding['tone']->value,
                'snippets' => (string) $this->json->serialize(array_map('strval', array_keys($finding['snippets']))),
                'pages' => $finding['pages'],
                'pages_by_kind' => (string) $this->json->serialize($finding['kinds']),
            ];
        }
        $connection = $this->tables->connection();
        $connection->insertOnDuplicate(
            $this->tables->name(CheckTables::FINDING),
            $rows,
            ['rule_code', 'path', 'sources', 'tone', 'snippets', 'pages', 'pages_by_kind']
        );
        if ($items !== []) {
            $this->writeItems($runId, $storeId, $items);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeAggregates(int $runId, int $storeId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->tables->connection()->insertOnDuplicate(
            $this->tables->name(CheckTables::AGGREGATE),
            array_map(
                static fn (array $row): array => [
                    'run_id' => $runId,
                    'store_id' => $storeId,
                    'metric' => $row['metric']->value,
                    'subject' => $row['subject'],
                    'value' => $row['value'],
                ],
                $rows
            ),
            ['value']
        );
    }

    /**
     * @inheritDoc
     */
    public function writeDistinctCounts(int $runId, array $pagesByKind): void
    {
        $rows = [];
        foreach ($pagesByKind as $kind => $pages) {
            $rows[] = ['metric' => AggregateMetric::Pages, 'subject' => (string) $kind, 'value' => $pages];
        }
        foreach ($this->distinctPages($this->toneSelect($runId, PropertyTone::Bad)) as $kind => $pages) {
            $rows[] = ['metric' => AggregateMetric::InvalidPages, 'subject' => $kind, 'value' => $pages];
        }
        $gaps = $this->toneSelect($runId, PropertyTone::Warn)
            ->where('NOT EXISTS (' . $this->sameEntitySelect($runId, PropertyTone::Bad)->assemble() . ')');
        foreach ($this->distinctPages($gaps) as $kind => $pages) {
            $rows[] = ['metric' => AggregateMetric::GapPages, 'subject' => $kind, 'value' => $pages];
        }
        $this->writeAggregates($runId, 0, $rows);
    }

    /**
     * Add the pages of a store view's findings, each once per finding
     *
     * @param int $runId
     * @param int $storeId
     * @param array<string,list<Item>> $items by finding key
     * @return void
     */
    private function writeItems(int $runId, int $storeId, array $items): void
    {
        $connection = $this->tables->connection();
        $ids = $connection->fetchPairs(
            $connection->select()
                ->from($this->tables->name(CheckTables::FINDING), ['finding_key', 'finding_id'])
                ->where('run_id = ?', $runId)
                ->where('store_id = ?', $storeId)
                ->where('finding_key IN (?)', array_map('strval', array_keys($items)))
        );
        $data = [];
        foreach ($items as $key => $pages) {
            $id = $ids[$key] ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            foreach ($pages as $page) {
                $data[] = ['finding_id' => (int) $id, 'kind' => $page['kind'], 'entity_id' => $page['entity']];
            }
        }
        if ($data !== []) {
            $connection->insertOnDuplicate($this->tables->name(CheckTables::ITEM), $data, ['kind']);
        }
    }

    /**
     * The pages of a run's findings of a tone, by kind, each page once
     *
     * @param Select $select
     * @return array<string, int>
     */
    private function distinctPages(Select $select): array
    {
        $counts = [];
        foreach ($this->tables->connection()->fetchAll($select) as $row) {
            if (is_array($row) && is_string($row['kind'] ?? null) && is_numeric($row[self::COUNTED] ?? null)) {
                $counts[$row['kind']] = (int) $row[self::COUNTED];
            }
        }

        return $counts;
    }

    /**
     * A count of the distinct pages of a run's findings of a tone, by kind
     *
     * @param int $runId
     * @param PropertyTone $tone
     * @return Select
     */
    private function toneSelect(int $runId, PropertyTone $tone): Select
    {
        return $this->tables->connection()->select()
            ->from(['item' => $this->tables->name(CheckTables::ITEM)], [
                'kind' => 'item.kind',
                self::COUNTED => 'COUNT(DISTINCT item.entity_id)',
            ])
            ->join(
                ['finding' => $this->tables->name(CheckTables::FINDING)],
                'finding.finding_id = item.finding_id',
                []
            )
            ->where('finding.run_id = ?', $runId)
            ->where('finding.tone = ?', $tone->value)
            ->group('item.kind');
    }

    /**
     * The same page among a run's findings of a tone, for an `EXISTS` test against the outer `item` row
     *
     * @param int $runId
     * @param PropertyTone $tone
     * @return Select
     */
    private function sameEntitySelect(int $runId, PropertyTone $tone): Select
    {
        return $this->tables->connection()->select()
            ->from(['other' => $this->tables->name(CheckTables::ITEM)], ['kind'])
            ->join(
                ['other_finding' => $this->tables->name(CheckTables::FINDING)],
                'other_finding.finding_id = other.finding_id',
                []
            )
            ->where('other_finding.run_id = ?', $runId)
            ->where('other_finding.tone = ?', $tone->value)
            ->where('other.kind = item.kind')
            ->where('other.entity_id = item.entity_id');
    }
}
