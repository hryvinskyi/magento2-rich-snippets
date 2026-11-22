<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckRunnerInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Model\Check\Mail\FindingsMailInterface;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FixedClock;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCheckRuns;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\RecordingLocks;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * The minute job runs a check only under its lock: it fails dead runs first, claims the requested one, runs it,
 * mails its findings, and always lets the lock go
 */
class RequestedChecksTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @var list<int> the runs the runner was asked to run
     */
    public array $ran = [];

    /**
     * @var list<int> the runs the mail was asked about
     */
    public array $mailed = [];

    /**
     * @var list<string> what was logged
     */
    public array $logged = [];

    public function testTheRequestedRunIsClaimedRunAndItsFindingsMailed(): void
    {
        $runs = new InMemoryCheckRuns();
        $id = $runs->add(CheckState::Requested, self::NOW - 30);
        $locks = new RecordingLocks();

        $ended = $this->job($runs, $locks)->runNext();

        self::assertSame([$id], $this->ran);
        self::assertSame(CheckState::Done, $ended?->getState());
        self::assertSame([$id], $this->mailed);
        self::assertSame(
            ['lock hryvinskyi_rich_snippets_catalog_check 0', 'unlock hryvinskyi_rich_snippets_catalog_check'],
            $locks->calls
        );
    }

    public function testWhileAnotherProcessHoldsTheLockNothingIsTouched(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Requested, self::NOW - 30);
        $stale = $runs->add(CheckState::Running, self::NOW - 7200, self::NOW - 3600);

        $ended = $this->job($runs, new RecordingLocks([RequestedChecks::LOCK]))->runNext();

        self::assertNull($ended);
        self::assertSame([], $this->ran);
        self::assertSame(CheckState::Running, $runs->rows[$stale]['state']);
    }

    public function testARunSilentForLongerThanAllowedIsFailedBeforeAnythingIsClaimed(): void
    {
        $runs = new InMemoryCheckRuns();
        $dead = $runs->add(CheckState::Running, self::NOW - 7200, self::NOW - 901);

        $ended = $this->job($runs, new RecordingLocks())->runNext();

        self::assertNull($ended);
        self::assertSame(CheckState::Failed, $runs->rows[$dead]['state']);
        self::assertSame('stale', $runs->rows[$dead]['failure']);
        self::assertSame([], $this->ran);
    }

    public function testARunStillReportingProgressIsLeftRunning(): void
    {
        $runs = new InMemoryCheckRuns();
        $alive = $runs->add(CheckState::Running, self::NOW - 7200, self::NOW - 899);

        $this->job($runs, new RecordingLocks())->runNext();

        self::assertSame(CheckState::Running, $runs->rows[$alive]['state']);
    }

    public function testARunAnotherProcessClaimedFirstIsNotRun(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Requested, self::NOW - 30);
        $runs->refuseClaims = true;

        self::assertNull($this->job($runs, new RecordingLocks())->runNext());
        self::assertSame([], $this->ran);
    }

    public function testAFailedRunIsNotMailedAndTheLockIsLetGo(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Requested, self::NOW - 30);
        $locks = new RecordingLocks();

        try {
            $this->job($runs, $locks, new \RuntimeException('The catalog could not be read.'))->runNext();
            self::fail('The failure goes on to cron.');
        } catch (\RuntimeException $e) {
            self::assertSame('The catalog could not be read.', $e->getMessage());
        }

        self::assertSame([], $this->mailed);
        self::assertSame([], $locks->held);
    }

    public function testAMailThatCannotBeSentIsLoggedAndTheRunStaysDone(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Requested, self::NOW - 30);

        $ended = $this->job($runs, new RecordingLocks(), null, true)->runNext();

        self::assertSame(CheckState::Done, $ended?->getState());
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('findings e-mail', $this->logged[0]);
    }

    /**
     * The job over fakes: a runner that finishes the run (or throws), a mail that records (or throws)
     *
     * @param InMemoryCheckRuns $runs
     * @param RecordingLocks $locks
     * @param \Throwable|null $runFailure
     * @param bool $mailFails
     * @return RequestedChecks
     */
    private function job(
        InMemoryCheckRuns $runs,
        RecordingLocks $locks,
        ?\Throwable $runFailure = null,
        bool $mailFails = false
    ): RequestedChecks {
        $runner = new class ($runs, $runFailure, $this) implements CatalogCheckRunnerInterface {
            /**
             * @param InMemoryCheckRuns $runs
             * @param \Throwable|null $failure
             * @param RequestedChecksTest $test
             */
            public function __construct(
                private readonly InMemoryCheckRuns $runs,
                private readonly ?\Throwable $failure,
                private readonly RequestedChecksTest $test
            ) {
            }

            public function run(CheckRunInterface $run): void
            {
                $this->test->ran[] = $run->getId();
                if ($this->failure !== null) {
                    $this->runs->fail($run->getId(), 'error', 0);
                    throw $this->failure;
                }
                $this->runs->finish($run->getId(), 0);
            }
        };
        $mail = new class ($mailFails, $this) implements FindingsMailInterface {
            /**
             * @param bool $fails
             * @param RequestedChecksTest $test
             */
            public function __construct(private readonly bool $fails, private readonly RequestedChecksTest $test)
            {
            }

            public function afterCheck(CheckRunInterface $run): bool
            {
                if ($this->fails) {
                    throw new \RuntimeException('No mail server.');
                }
                $this->test->mailed[] = $run->getId();

                return true;
            }
        };
        $logger = new class ($this) extends AbstractLogger {
            /**
             * @param RequestedChecksTest $test
             */
            public function __construct(private readonly RequestedChecksTest $test)
            {
            }

            /**
             * @param mixed $level
             * @param string|\Stringable $message
             * @param array<mixed> $context
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->test->logged[] = (string) $message;
            }
        };

        return new RequestedChecks($locks, $runs, $runner, $mail, new FixedClock(self::NOW), $logger);
    }
}
