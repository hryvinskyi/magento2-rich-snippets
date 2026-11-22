<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\CatalogCheckRunnerInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection\ConnectionFactory;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\ExpressionConverter;
use Magento\Framework\Lock\Backend\Database as DatabaseLocks;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * The life of a catalog check in the database: one pending run however often it is asked for, one process that takes
 * it, a run that went silent failed by the next job, and a job that finds another one running doing nothing
 *
 * The runner is replaced by one that records what it was given, so the tests are about who runs and when, not about
 * what a run finds. The test framework swaps the database locks for ones that always succeed, so the job is given the
 * platform's MySQL locks here, as a shop runs it. Each test starts with no runs, whatever the database kept.
 */
#[DbIsolation(true)]
class CheckRunLifecycleTest extends TestCase
{
    private const HOUR = 3600;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var list<int> the runs the recording runner was given
     */
    public array $ran = [];

    /**
     * @var \Closure|null what the recording runner does while it runs
     */
    public ?\Closure $during = null;

    /**
     * @var AdapterInterface|null a connection of another process, when a test opened one
     */
    private ?AdapterInterface $otherProcess = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $resource = $this->get(ResourceConnection::class);
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->otherProcess !== null) {
            $this->otherProcess->query('SELECT RELEASE_ALL_LOCKS()');
            if ($this->otherProcess instanceof \Zend_Db_Adapter_Abstract) {
                $this->otherProcess->closeConnection();
            }
            $this->otherProcess = null;
        }
    }

    public function testAskingTwiceLeavesOneRunWithTheFirstTrigger(): void
    {
        $check = $this->get(CatalogCheckInterface::class);

        $first = $check->request(CheckTrigger::Import);
        $second = $check->request(CheckTrigger::Manual);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(CheckTrigger::Import, $second->getTrigger());
        self::assertTrue($second->isPending());
    }

    public function testOnlyOneProcessTakesARequestedRun(): void
    {
        $runs = $this->get(CheckRunStoreInterface::class);
        $run = $runs->request(CheckTrigger::Manual, time());

        self::assertTrue($runs->claim($run->getId(), time()));
        self::assertFalse($runs->claim($run->getId(), time()));
        self::assertSame(CheckState::Running, $runs->get($run->getId())?->getState());
        $again = $runs->request(CheckTrigger::Manual, time());
        self::assertSame($run->getId(), $again->getId(), 'A running run is pending.');
    }

    public function testARunThatWentSilentIsFailedAndTheNextRequestRuns(): void
    {
        $runs = $this->get(CheckRunStoreInterface::class);
        $silent = $runs->request(CheckTrigger::Schedule, time() - self::HOUR);
        $runs->claim($silent->getId(), time() - self::HOUR);
        $runs->start($silent->getId(), 100, 1, time() - self::HOUR);

        $next = $runs->request(CheckTrigger::Manual, time());
        self::assertSame($silent->getId(), $next->getId(), 'The silent run still counts as pending.');
        $ended = $this->checks()->runNext();

        $failed = $runs->get($silent->getId());
        self::assertSame(CheckState::Failed, $failed?->getState());
        self::assertSame('stale', $failed->getFailure());
        self::assertFalse($failed->isPending());
        self::assertNull($ended, 'Nothing else was asked for.');
        $fresh = $runs->request(CheckTrigger::Manual, time());
        self::assertNotSame($silent->getId(), $fresh->getId());
        self::assertSame($fresh->getId(), $this->checks()->runNext()?->getId());
        self::assertSame([$fresh->getId()], $this->ran);
    }

    public function testAJobThatStartsWhileACheckRunsRunsNothing(): void
    {
        $run = $this->get(CatalogCheckInterface::class)->request(CheckTrigger::Manual);
        $checks = $this->checks();
        $lockedElsewhere = null;
        $nested = 'not asked';
        $this->during = function () use ($checks, &$lockedElsewhere, &$nested): void {
            $lockedElsewhere = $this->lockFromAnotherProcess();
            $this->get(CatalogCheckInterface::class)->request(CheckTrigger::Schedule);
            $nested = $checks->runNext();
        };

        $ended = $checks->runNext();

        self::assertSame($run->getId(), $ended?->getId());
        self::assertSame([$run->getId()], $this->ran, 'The check ran once.');
        self::assertFalse($lockedElsewhere, 'Another process could not start a check while this one ran.');
        self::assertNull($nested, 'A second job in the same process found nothing to run.');
    }

    public function testAJobFindingTheLockTakenLeavesTheRequestForLater(): void
    {
        $run = $this->get(CatalogCheckInterface::class)->request(CheckTrigger::Manual);
        self::assertTrue($this->lockFromAnotherProcess(), 'The other process holds the lock.');

        $ended = $this->checks()->runNext();

        self::assertNull($ended);
        self::assertSame([], $this->ran);
        $kept = $this->get(CheckRunStoreInterface::class)->get($run->getId());
        self::assertSame(CheckState::Requested, $kept?->getState());
    }

    /**
     * The minute job's service with the recording runner
     *
     * @return RequestedChecks
     */
    private function checks(): RequestedChecks
    {
        $test = $this;
        $runner = new class ($test) implements CatalogCheckRunnerInterface {
            /**
             * @param CheckRunLifecycleTest $test
             */
            public function __construct(private readonly CheckRunLifecycleTest $test)
            {
            }

            /**
             * @inheritDoc
             */
            public function run(CheckRunInterface $run): void
            {
                $this->test->ran[] = $run->getId();
                if ($this->test->during !== null) {
                    ($this->test->during)();
                }
                $runs = Bootstrap::getObjectManager()->get(CheckRunStoreInterface::class);
                Assert::assertInstanceOf(CheckRunStoreInterface::class, $runs);
                $runs->start($run->getId(), 0, 1, time());
                $runs->finish($run->getId(), time());
            }
        };
        $locks = new DatabaseLocks($this->get(ResourceConnection::class), $this->get(DeploymentConfig::class));
        $checks = $this->objectManager->create(RequestedChecks::class, ['locks' => $locks, 'runner' => $runner]);
        self::assertInstanceOf(RequestedChecks::class, $checks);

        return $checks;
    }

    /**
     * Try to take the catalog check's lock from another database connection, as another process would
     *
     * @return bool whether it was taken
     */
    private function lockFromAnotherProcess(): bool
    {
        $deployment = $this->get(DeploymentConfig::class);
        $config = $deployment->get(ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT);
        self::assertIsArray($config);
        $this->otherProcess ??= $this->get(ConnectionFactory::class)->create($config);
        $database = $deployment->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT . '/' . ConfigOptionsListConstants::KEY_NAME,
            ''
        );
        self::assertIsString($database);
        $prefix = $database === '' ? '' : $database . '|';
        $name = ExpressionConverter::shortenEntityName($prefix . RequestedChecks::LOCK, $prefix);

        return (bool) $this->otherProcess->query('SELECT GET_LOCK(?, 0)', [$name])->fetchColumn();
    }

    /**
     * A service from the container
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
