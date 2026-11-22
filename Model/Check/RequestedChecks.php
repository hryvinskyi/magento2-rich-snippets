<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckRunnerInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Model\Check\Mail\FindingsMailInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The one place that runs catalog checks: the job that starts every minute takes the requested run, if any
 *
 * Under a lock no other process holds, so two jobs that overlap never run two checks: the second one finds the lock
 * taken and returns at once. Holding it, the job first fails every running run that has shown no sign of life for a
 * while (its process died, and it would otherwise show as running for ever), then claims the requested run with a
 * conditional update and runs it. After a run that finished, the findings e-mail goes out; a failure to send it is
 * logged and changes nothing about the run.
 */
class RequestedChecks
{
    public const LOCK = 'hryvinskyi_rich_snippets_catalog_check';
    private const NO_WAIT = 0;

    /**
     * @param LockManagerInterface $locks
     * @param CheckRunStoreInterface $runs
     * @param CatalogCheckRunnerInterface $runner
     * @param FindingsMailInterface $mail
     * @param ClockInterface $clock
     * @param LoggerInterface $logger
     * @param int $staleAfter seconds without progress after which a running run counts as dead
     */
    public function __construct(
        private readonly LockManagerInterface $locks,
        private readonly CheckRunStoreInterface $runs,
        private readonly CatalogCheckRunnerInterface $runner,
        private readonly FindingsMailInterface $mail,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly int $staleAfter = 900
    ) {
    }

    /**
     * Run the requested check, if there is one and no other process runs checks now
     *
     * @return CheckRunInterface|null the run as it ended; null when nothing was run
     * @throws \Throwable whatever made the run fail, after it was marked failed
     */
    public function runNext(): ?CheckRunInterface
    {
        if (!$this->locks->lock(self::LOCK, self::NO_WAIT)) {
            return null;
        }
        try {
            $now = $this->clock->now();
            $this->runs->failStale($now - $this->staleAfter, $now);
            $run = $this->runs->nextRequested();
            if ($run === null || !$this->runs->claim($run->getId(), $now)) {
                return null;
            }
            $this->runner->run($run);
            $ended = $this->runs->get($run->getId()) ?? $run;
            $this->mail($ended);

            return $ended;
        } finally {
            $this->locks->unlock(self::LOCK);
        }
    }

    /**
     * Send the findings e-mail of a run, logging a failure to send it
     *
     * @param CheckRunInterface $run
     * @return void
     */
    private function mail(CheckRunInterface $run): void
    {
        try {
            $this->mail->afterCheck($run);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Rich snippets: the findings e-mail of a catalog check was not sent: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
