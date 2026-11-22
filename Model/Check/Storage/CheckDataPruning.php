<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Storage;

use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;

/**
 * Drops what the catalog checks and the admin's writes no longer need
 *
 * Runs asked for more than 30 days ago go with their findings and counts. The pages of the findings are kept only
 * for the last finished run and the pending one; older runs keep their findings and counts without them, so nightly
 * runs never pile up a row per page each. The idempotency keys of the admin's creating writes go after 7 days, when
 * no retry of the same click can come any more.
 */
class CheckDataPruning
{
    private const IDEMPOTENCY_TABLE = 'hryvinskyi_rich_snippets_idempotency';
    private const TIME_FORMAT = 'Y-m-d H:i:s';
    private const DAY = 86400;

    /**
     * @param CheckTables $tables
     * @param ClockInterface $clock
     * @param int $runDays how long runs are kept
     * @param int $idempotencyDays how long idempotency keys are kept
     */
    public function __construct(
        private readonly CheckTables $tables,
        private readonly ClockInterface $clock,
        private readonly int $runDays = 30,
        private readonly int $idempotencyDays = 7
    ) {
    }

    /**
     * Drop everything that has outlived its use
     *
     * @return void
     */
    public function prune(): void
    {
        $now = $this->clock->now();
        $connection = $this->tables->connection();
        $connection->delete(
            $this->tables->name(CheckTables::RUN),
            ['requested_at < ?' => gmdate(self::TIME_FORMAT, $now - $this->runDays * self::DAY)]
        );
        $kept = $this->keptRuns();
        $findings = $connection->select()
            ->from($this->tables->name(CheckTables::FINDING), ['finding_id']);
        if ($kept !== []) {
            $findings->where('run_id NOT IN (?)', $kept);
        }
        $connection->delete($this->tables->name(CheckTables::ITEM), 'finding_id IN (' . $findings->assemble() . ')');
        $connection->delete(
            $this->tables->name(self::IDEMPOTENCY_TABLE),
            ['created_at < ?' => gmdate(self::TIME_FORMAT, $now - $this->idempotencyDays * self::DAY)]
        );
    }

    /**
     * The runs whose findings keep their pages: the last finished and the pending one
     *
     * @return list<int>
     */
    private function keptRuns(): array
    {
        $connection = $this->tables->connection();
        $table = $this->tables->name(CheckTables::RUN);
        $last = $connection->fetchOne(
            $connection->select()
                ->from($table, ['run_id'])
                ->where('state = ?', CheckState::Done->value)
                ->order(['finished_at DESC', 'run_id DESC'])
                ->limit(1)
        );
        $pending = $connection->fetchCol(
            $connection->select()->from($table, ['run_id'])->where('pending IS NOT NULL')
        );

        return array_values(array_map('intval', array_filter([$last, ...$pending], 'is_numeric')));
    }
}
