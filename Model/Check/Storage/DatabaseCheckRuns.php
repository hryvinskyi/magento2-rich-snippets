<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Storage;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * Keeps the catalog checks in a table, one row per run
 *
 * A pending run carries 1 in a column with a unique index, which every other run leaves empty: a second pending run
 * cannot be inserted (the insert of a request meets the pending row and leaves it as it is), so two requests at
 * once yield one run. Each move of a run is an update conditional on the
 * state it moves from, so a claim succeeds for one process only. Times are kept in UTC.
 */
class DatabaseCheckRuns implements CheckRunStoreInterface
{
    private const TABLE = 'hryvinskyi_rich_snippets_check_run';
    private const TIME_FORMAT = 'Y-m-d H:i:s';
    private const PENDING = 1;
    private const ATTEMPTS = 3;

    /**
     * @param ResourceConnection $resource
     * @param CheckRunInterfaceFactory $runs
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CheckRunInterfaceFactory $runs
    ) {
    }

    /**
     * @inheritDoc
     */
    public function request(CheckTrigger $trigger, int $now): CheckRunInterface
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $this->connection()->insertOnDuplicate(
                $this->table(),
                [
                    'state' => CheckState::Requested->value,
                    'triggered_by' => $trigger->value,
                    'pending' => self::PENDING,
                    'requested_at' => $this->time($now),
                ],
                ['pending']
            );
            $pending = $this->pending();
            if ($pending !== null) {
                return $pending;
            }
        }

        throw new \RuntimeException('A catalog check could not be requested.');
    }

    /**
     * @inheritDoc
     */
    public function get(int $runId): ?CheckRunInterface
    {
        return $this->one($this->select()->where('run_id = ?', $runId));
    }

    /**
     * @inheritDoc
     */
    public function pending(): ?CheckRunInterface
    {
        return $this->one($this->select()->where('pending = ?', self::PENDING));
    }

    /**
     * @inheritDoc
     */
    public function nextRequested(): ?CheckRunInterface
    {
        return $this->one(
            $this->select()->where('state = ?', CheckState::Requested->value)->order('run_id ASC')
        );
    }

    /**
     * @inheritDoc
     */
    public function claim(int $runId, int $now): bool
    {
        return $this->connection()->update(
            $this->table(),
            [
                'state' => CheckState::Running->value,
                'started_at' => $this->time($now),
                'heartbeat_at' => $this->time($now),
            ],
            ['run_id = ?' => $runId, 'state = ?' => CheckState::Requested->value]
        ) === 1;
    }

    /**
     * @inheritDoc
     */
    public function start(int $runId, int $pagesTotal, int $storeViews, int $now): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'pages_total' => $pagesTotal,
                'pages_checked' => 0,
                'store_views' => $storeViews,
                'heartbeat_at' => $this->time($now),
            ],
            ['run_id = ?' => $runId, 'state = ?' => CheckState::Running->value]
        );
    }

    /**
     * @inheritDoc
     */
    public function progress(int $runId, int $pagesChecked, int $now): void
    {
        $this->connection()->update(
            $this->table(),
            ['pages_checked' => $pagesChecked, 'heartbeat_at' => $this->time($now)],
            ['run_id = ?' => $runId, 'state = ?' => CheckState::Running->value]
        );
    }

    /**
     * @inheritDoc
     */
    public function finish(int $runId, int $now): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'state' => CheckState::Done->value,
                'pending' => null,
                'finished_at' => $this->time($now),
                'heartbeat_at' => $this->time($now),
            ],
            ['run_id = ?' => $runId, 'state = ?' => CheckState::Running->value]
        );
    }

    /**
     * @inheritDoc
     */
    public function fail(int $runId, string $reason, int $now): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'state' => CheckState::Failed->value,
                'pending' => null,
                'failure' => $reason,
                'finished_at' => $this->time($now),
            ],
            ['run_id = ?' => $runId, 'pending = ?' => self::PENDING]
        );
    }

    /**
     * @inheritDoc
     */
    public function failStale(int $silentSince, int $now): int
    {
        return $this->connection()->update(
            $this->table(),
            [
                'state' => CheckState::Failed->value,
                'pending' => null,
                'failure' => 'stale',
                'finished_at' => $this->time($now),
            ],
            ['state = ?' => CheckState::Running->value, 'heartbeat_at < ?' => $this->time($silentSince)]
        );
    }

    /**
     * @inheritDoc
     */
    public function latest(CheckState $state): ?CheckRunInterface
    {
        return $this->one(
            $this->select()->where('state = ?', $state->value)->order(['finished_at DESC', 'run_id DESC'])
        );
    }

    /**
     * @inheritDoc
     */
    public function newest(): ?CheckRunInterface
    {
        return $this->one($this->select()->order('run_id DESC'));
    }

    /**
     * The first run a select finds
     *
     * @param Select $select
     * @return CheckRunInterface|null
     */
    private function one(Select $select): ?CheckRunInterface
    {
        $row = $this->connection()->fetchRow($select->limit(1));

        return is_array($row) ? $this->run($row) : null;
    }

    /**
     * A run from its row
     *
     * @param array<mixed> $row
     * @return CheckRunInterface|null null for a row whose state or trigger no longer reads
     */
    private function run(array $row): ?CheckRunInterface
    {
        $state = CheckState::tryFrom(is_string($row['state'] ?? null) ? $row['state'] : '');
        $trigger = CheckTrigger::tryFrom(is_string($row['triggered_by'] ?? null) ? $row['triggered_by'] : '');
        if ($state === null || $trigger === null) {
            return null;
        }

        return $this->runs->create([
            'id' => $this->number($row['run_id'] ?? null),
            'state' => $state,
            'trigger' => $trigger,
            'requestedAt' => $this->seconds($row['requested_at'] ?? null) ?? 0,
            'startedAt' => $this->seconds($row['started_at'] ?? null),
            'finishedAt' => $this->seconds($row['finished_at'] ?? null),
            'heartbeatAt' => $this->seconds($row['heartbeat_at'] ?? null),
            'pagesTotal' => $this->number($row['pages_total'] ?? null),
            'pagesChecked' => $this->number($row['pages_checked'] ?? null),
            'storeViews' => $this->number($row['store_views'] ?? null),
            'failure' => is_string($row['failure'] ?? null) ? $row['failure'] : null,
        ]);
    }

    /**
     * Unix seconds of a stored UTC time
     *
     * @param mixed $time
     * @return int|null
     */
    private function seconds(mixed $time): ?int
    {
        if (!is_string($time) || $time === '') {
            return null;
        }
        $seconds = strtotime($time . ' UTC');

        return $seconds === false ? null : $seconds;
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

    /**
     * A time as it is stored, in UTC
     *
     * @param int $seconds
     * @return string
     */
    private function time(int $seconds): string
    {
        return gmdate(self::TIME_FORMAT, $seconds);
    }

    /**
     * A select of every column of the runs
     *
     * @return Select
     */
    private function select(): Select
    {
        return $this->connection()->select()->from($this->table());
    }

    /**
     * The connection the runs are kept through
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * The real name of the runs' table
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
