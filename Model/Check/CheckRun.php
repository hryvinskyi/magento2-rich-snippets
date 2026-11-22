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
 * One catalog check as it was stored when it was read
 */
class CheckRun implements CheckRunInterface
{
    /**
     * @param int $id
     * @param CheckState $state
     * @param CheckTrigger $trigger
     * @param int $requestedAt
     * @param int|null $startedAt
     * @param int|null $finishedAt
     * @param int|null $heartbeatAt
     * @param int $pagesTotal
     * @param int $pagesChecked
     * @param int $storeViews
     * @param string|null $failure
     */
    public function __construct(
        private readonly int $id,
        private readonly CheckState $state,
        private readonly CheckTrigger $trigger,
        private readonly int $requestedAt,
        private readonly ?int $startedAt = null,
        private readonly ?int $finishedAt = null,
        private readonly ?int $heartbeatAt = null,
        private readonly int $pagesTotal = 0,
        private readonly int $pagesChecked = 0,
        private readonly int $storeViews = 0,
        private readonly ?string $failure = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getState(): CheckState
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function getTrigger(): CheckTrigger
    {
        return $this->trigger;
    }

    /**
     * @inheritDoc
     */
    public function getRequestedAt(): int
    {
        return $this->requestedAt;
    }

    /**
     * @inheritDoc
     */
    public function getStartedAt(): ?int
    {
        return $this->startedAt;
    }

    /**
     * @inheritDoc
     */
    public function getFinishedAt(): ?int
    {
        return $this->finishedAt;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatAt(): ?int
    {
        return $this->heartbeatAt;
    }

    /**
     * @inheritDoc
     */
    public function getPagesTotal(): int
    {
        return $this->pagesTotal;
    }

    /**
     * @inheritDoc
     */
    public function getPagesChecked(): int
    {
        return $this->pagesChecked;
    }

    /**
     * @inheritDoc
     */
    public function getStoreViews(): int
    {
        return $this->storeViews;
    }

    /**
     * @inheritDoc
     */
    public function getFailure(): ?string
    {
        return $this->failure;
    }

    /**
     * @inheritDoc
     */
    public function isPending(): bool
    {
        return $this->state === CheckState::Requested || $this->state === CheckState::Running;
    }

    /**
     * @inheritDoc
     */
    public function getDuration(): ?int
    {
        if ($this->startedAt === null || $this->finishedAt === null) {
            return null;
        }

        return max(0, $this->finishedAt - $this->startedAt);
    }
}
