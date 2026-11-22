<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One catalog check: when it was asked for, run and finished, and how far it got
 *
 * Times are Unix seconds. A page is counted once per store view it is checked in.
 *
 * @api
 */
interface CheckRunInterface
{
    /**
     * The run's ID
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Where the run is in its life
     *
     * @return CheckState
     */
    public function getState(): CheckState;

    /**
     * What asked for the run
     *
     * @return CheckTrigger
     */
    public function getTrigger(): CheckTrigger;

    /**
     * When the run was asked for
     *
     * @return int
     */
    public function getRequestedAt(): int;

    /**
     * When the run started; null while it waits
     *
     * @return int|null
     */
    public function getStartedAt(): ?int;

    /**
     * When the run ended, done or failed; null while it is pending
     *
     * @return int|null
     */
    public function getFinishedAt(): ?int;

    /**
     * When the running check last reported progress; null before it started
     *
     * @return int|null
     */
    public function getHeartbeatAt(): ?int;

    /**
     * The pages the run checks, one per page and store view; 0 until it has counted them
     *
     * @return int
     */
    public function getPagesTotal(): int;

    /**
     * The pages checked so far, one per page and store view
     *
     * @return int
     */
    public function getPagesChecked(): int;

    /**
     * The store views the run checks; 0 until it has started
     *
     * @return int
     */
    public function getStoreViews(): int;

    /**
     * Why a failed run stopped (`stale`, `error`); null for any other run
     *
     * @return string|null
     */
    public function getFailure(): ?string;

    /**
     * Whether the run is requested or running
     *
     * @return bool
     */
    public function isPending(): bool;

    /**
     * How long the run took, from its start to its end; null while it has not both started and ended
     *
     * @return int|null seconds
     */
    public function getDuration(): ?int;
}
