<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\CheckRun;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;

/**
 * Catalog checks kept in memory, moving as the table moves them
 *
 * @phpstan-type Row array{state: CheckState, trigger: CheckTrigger, requestedAt: int, startedAt: int|null,
 *     finishedAt: int|null, heartbeatAt: int|null, pagesTotal: int, pagesChecked: int, storeViews: int,
 *     failure: string|null}
 */
class InMemoryCheckRuns implements CheckRunStoreInterface
{
    /**
     * @var array<int, Row> by ID
     */
    public array $rows = [];

    /**
     * Whether a claim is refused, as when another process took the run first
     *
     * @var bool
     */
    public bool $refuseClaims = false;

    /**
     * Add a run as it would be stored
     *
     * @param CheckState $state
     * @param int $requestedAt
     * @param int|null $heartbeatAt
     * @param CheckTrigger $trigger
     * @return int its ID
     */
    public function add(
        CheckState $state,
        int $requestedAt = 0,
        ?int $heartbeatAt = null,
        CheckTrigger $trigger = CheckTrigger::Manual
    ): int {
        $id = count($this->rows) + 1;
        $this->rows[$id] = [
            'state' => $state,
            'trigger' => $trigger,
            'requestedAt' => $requestedAt,
            'startedAt' => $heartbeatAt,
            'finishedAt' => null,
            'heartbeatAt' => $heartbeatAt,
            'pagesTotal' => 0,
            'pagesChecked' => 0,
            'storeViews' => 0,
            'failure' => null,
        ];

        return $id;
    }

    /**
     * @inheritDoc
     */
    public function request(CheckTrigger $trigger, int $now): CheckRunInterface
    {
        return $this->pending() ?? $this->runOf($this->add(CheckState::Requested, $now, null, $trigger));
    }

    /**
     * @inheritDoc
     */
    public function get(int $runId): ?CheckRunInterface
    {
        return isset($this->rows[$runId]) ? $this->runOf($runId) : null;
    }

    /**
     * @inheritDoc
     */
    public function pending(): ?CheckRunInterface
    {
        foreach ($this->rows as $id => $row) {
            if ($row['state'] === CheckState::Requested || $row['state'] === CheckState::Running) {
                return $this->runOf($id);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function nextRequested(): ?CheckRunInterface
    {
        foreach ($this->rows as $id => $row) {
            if ($row['state'] === CheckState::Requested) {
                return $this->runOf($id);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function claim(int $runId, int $now): bool
    {
        if ($this->refuseClaims || !isset($this->rows[$runId])
            || $this->rows[$runId]['state'] !== CheckState::Requested
        ) {
            return false;
        }
        $row = $this->rows[$runId];
        $row['state'] = CheckState::Running;
        $row['startedAt'] = $now;
        $row['heartbeatAt'] = $now;
        $this->rows[$runId] = $row;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function start(int $runId, int $pagesTotal, int $storeViews, int $now): void
    {
        if (!isset($this->rows[$runId])) {
            return;
        }
        $row = $this->rows[$runId];
        $row['pagesTotal'] = $pagesTotal;
        $row['storeViews'] = $storeViews;
        $row['heartbeatAt'] = $now;
        $this->rows[$runId] = $row;
    }

    /**
     * @inheritDoc
     */
    public function progress(int $runId, int $pagesChecked, int $now): void
    {
        if (!isset($this->rows[$runId])) {
            return;
        }
        $row = $this->rows[$runId];
        $row['pagesChecked'] = $pagesChecked;
        $row['heartbeatAt'] = $now;
        $this->rows[$runId] = $row;
    }

    /**
     * @inheritDoc
     */
    public function finish(int $runId, int $now): void
    {
        if (!isset($this->rows[$runId])) {
            return;
        }
        $row = $this->rows[$runId];
        $row['state'] = CheckState::Done;
        $row['finishedAt'] = $now;
        $this->rows[$runId] = $row;
    }

    /**
     * @inheritDoc
     */
    public function fail(int $runId, string $reason, int $now): void
    {
        if (!isset($this->rows[$runId])) {
            return;
        }
        $row = $this->rows[$runId];
        $row['state'] = CheckState::Failed;
        $row['failure'] = $reason;
        $row['finishedAt'] = $now;
        $this->rows[$runId] = $row;
    }

    /**
     * @inheritDoc
     */
    public function failStale(int $silentSince, int $now): int
    {
        $failed = 0;
        foreach ($this->rows as $id => $row) {
            if ($row['state'] === CheckState::Running && ($row['heartbeatAt'] ?? 0) < $silentSince) {
                $this->fail($id, 'stale', $now);
                $failed++;
            }
        }

        return $failed;
    }

    /**
     * @inheritDoc
     */
    public function latest(CheckState $state): ?CheckRunInterface
    {
        $found = null;
        foreach ($this->rows as $id => $row) {
            if ($row['state'] === $state) {
                $found = $id;
            }
        }

        return $found === null ? null : $this->runOf($found);
    }

    /**
     * @inheritDoc
     */
    public function newest(): ?CheckRunInterface
    {
        $ids = array_keys($this->rows);

        return $ids === [] ? null : $this->runOf(max($ids));
    }

    /**
     * The run with an ID
     *
     * @param int $id
     * @return CheckRunInterface
     */
    private function runOf(int $id): CheckRunInterface
    {
        $row = $this->rows[$id];

        return new CheckRun(
            $id,
            $row['state'],
            $row['trigger'],
            $row['requestedAt'],
            $row['startedAt'],
            $row['finishedAt'],
            $row['heartbeatAt'],
            $row['pagesTotal'],
            $row['pagesChecked'],
            $row['storeViews'],
            $row['failure']
        );
    }
}
