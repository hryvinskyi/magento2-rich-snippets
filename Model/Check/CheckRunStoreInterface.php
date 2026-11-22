<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;

/**
 * Keeps the catalog checks and moves each through its life
 *
 * At most one run is pending (requested or running) at any time, whatever number of processes ask at once. Each
 * move is one conditional statement, so of two processes making the same move only one succeeds. Times are Unix
 * seconds.
 */
interface CheckRunStoreInterface
{
    /**
     * Ask for a run: the pending one when there is one, else a new requested run
     *
     * @param CheckTrigger $trigger what asks, kept on a new run only
     * @param int $now
     * @return CheckRunInterface
     */
    public function request(CheckTrigger $trigger, int $now): CheckRunInterface;

    /**
     * A run by its ID
     *
     * @param int $runId
     * @return CheckRunInterface|null null when there is no such run
     */
    public function get(int $runId): ?CheckRunInterface;

    /**
     * The requested or running run
     *
     * @return CheckRunInterface|null null when none is pending
     */
    public function pending(): ?CheckRunInterface;

    /**
     * The requested run waiting longest
     *
     * @return CheckRunInterface|null null when none waits
     */
    public function nextRequested(): ?CheckRunInterface;

    /**
     * Take a requested run to run it
     *
     * @param int $runId
     * @param int $now
     * @return bool false when the run is not requested any more: another process took it, or it ended
     */
    public function claim(int $runId, int $now): bool;

    /**
     * Record what a running run is about to check
     *
     * @param int $runId
     * @param int $pagesTotal one per page and store view
     * @param int $storeViews
     * @param int $now
     * @return void
     */
    public function start(int $runId, int $pagesTotal, int $storeViews, int $now): void;

    /**
     * Record how far a running run got; the time is its sign of life
     *
     * @param int $runId
     * @param int $pagesChecked
     * @param int $now
     * @return void
     */
    public function progress(int $runId, int $pagesChecked, int $now): void;

    /**
     * End a running run as done
     *
     * @param int $runId
     * @param int $now
     * @return void
     */
    public function finish(int $runId, int $now): void;

    /**
     * End a pending run as failed
     *
     * @param int $runId
     * @param string $reason `stale`, `error`
     * @param int $now
     * @return void
     */
    public function fail(int $runId, string $reason, int $now): void;

    /**
     * End as failed every running run with no sign of life since a time
     *
     * @param int $silentSince
     * @param int $now
     * @return int how many runs were failed
     */
    public function failStale(int $silentSince, int $now): int;

    /**
     * The run that ended last in a state
     *
     * @param CheckState $state
     * @return CheckRunInterface|null
     */
    public function latest(CheckState $state): ?CheckRunInterface;

    /**
     * The run asked for last, whatever its state
     *
     * @return CheckRunInterface|null
     */
    public function newest(): ?CheckRunInterface;
}
