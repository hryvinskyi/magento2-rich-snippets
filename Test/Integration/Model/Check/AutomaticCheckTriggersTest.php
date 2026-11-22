<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Cron\PruneCheckData;
use Hryvinskyi\RichSnippets\Cron\RunRequestedCheck;
use Hryvinskyi\RichSnippets\Cron\ScheduleCheck;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\Config\Backend\CheckTime;
use Magento\Cron\Model\ConfigInterface as CronConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * What asks for a catalog check without a person: the nightly job and the end of a product import, each only while
 * the nightly check is on, and each leaving one pending run however often it asks; and the cron jobs the shop runs
 */
#[DbIsolation(true)]
class AutomaticCheckTriggersTest extends TestCase
{
    private const GROUP = 'hryvinskyi_rich_snippets';
    private const IMPORT_FINISHED = 'catalog_product_import_finish_before';
    private const ENABLED = 'hryvinskyi_rich_snippets/check/enabled';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $resource = $this->get(ResourceConnection::class);
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
    }

    #[Config(self::ENABLED, '1')]
    public function testTheNightlyJobAsksForOneCheck(): void
    {
        $this->get(ScheduleCheck::class)->execute();
        $this->get(ScheduleCheck::class)->execute();

        $pending = $this->pending();
        self::assertSame(CheckTrigger::Schedule, $pending?->getTrigger());
        self::assertSame(CheckState::Requested, $pending->getState());
        self::assertSame(1, $this->runCount());
    }

    #[Config(self::ENABLED, '0')]
    public function testTheNightlyJobDoesNothingWhileTheCheckIsOff(): void
    {
        $this->get(ScheduleCheck::class)->execute();

        self::assertNull($this->pending());
    }

    #[Config(self::ENABLED, '1')]
    public function testTheEndOfAProductImportAsksForOneCheck(): void
    {
        $events = $this->get(EventManagerInterface::class);

        $events->dispatch(self::IMPORT_FINISHED, []);
        $events->dispatch(self::IMPORT_FINISHED, []);

        self::assertSame(CheckTrigger::Import, $this->pending()?->getTrigger());
        self::assertSame(1, $this->runCount());
    }

    #[Config(self::ENABLED, '0')]
    public function testAnImportAsksForNothingWhileTheCheckIsOff(): void
    {
        $this->get(EventManagerInterface::class)->dispatch(self::IMPORT_FINISHED, []);

        self::assertNull($this->pending());
    }

    public function testTheShopRunsTheJobsOfTheCheckInTheirOwnGroup(): void
    {
        $runRequested = $this->job('hryvinskyi_rich_snippets_run_requested');
        $schedule = $this->job('hryvinskyi_rich_snippets_schedule_check');

        self::assertSame(
            [RunRequestedCheck::class, '* * * * *'],
            [$runRequested['instance'] ?? null, $runRequested['schedule'] ?? null]
        );
        self::assertSame(
            [ScheduleCheck::class, CheckTime::CRON_EXPRESSION_PATH],
            [$schedule['instance'] ?? null, $schedule['config_path'] ?? null]
        );
        self::assertSame(PruneCheckData::class, $this->job('hryvinskyi_rich_snippets_prune')['instance'] ?? null);
    }

    /**
     * A job of the module's cron group as the shop reads it
     *
     * @param string $name
     * @return array<mixed>
     */
    private function job(string $name): array
    {
        $jobs = $this->get(CronConfigInterface::class)->getJobs();
        $group = $jobs[self::GROUP] ?? null;
        self::assertIsArray($group, 'The module has its own cron group.');
        $job = $group[$name] ?? null;
        self::assertIsArray($job, $name . ' is in the group.');

        return $job;
    }

    /**
     * The pending run, if there is one
     *
     * @return CheckRunInterface|null
     */
    private function pending(): ?CheckRunInterface
    {
        return $this->get(CheckRunStoreInterface::class)->pending();
    }

    /**
     * How many runs there are
     *
     * @return int
     */
    private function runCount(): int
    {
        $resource = $this->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($resource->getTableName(CheckTables::RUN), ['COUNT(*)'])
        );
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
