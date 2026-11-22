<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Cron\PruneCheckData;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The nightly clean-up of catalog checks: a run asked for more than thirty days ago goes with everything it found,
 * the pages behind a finding are kept only for the last finished run and a pending one, and idempotency keys older
 * than a week go
 */
#[DbIsolation(true)]
class CheckDataPruningTest extends TestCase
{
    private const DAY = 86400;
    private const IDEMPOTENCY_TABLE = 'hryvinskyi_rich_snippets_idempotency';

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

    public function testOnlyTheLastFinishedAndThePendingRunKeepTheirPages(): void
    {
        $now = time();
        $ancient = $this->finishedRun($now - 31 * self::DAY);
        $earlier = $this->finishedRun($now - 2 * self::DAY);
        $last = $this->finishedRun($now - self::DAY);
        $pending = $this->runs()->request(CheckTrigger::Manual, $now)->getId();
        $findings = [];
        foreach ([$ancient, $earlier, $last, $pending] as $runId) {
            $findings[$runId] = $this->findingWithPages($runId);
        }
        $this->idempotencyKey('rs-prune-old', $now - 8 * self::DAY);
        $this->idempotencyKey('rs-prune-new', $now - 6 * self::DAY);

        $this->get(PruneCheckData::class)->execute();

        self::assertNull($this->runs()->get($ancient), 'A run asked for over thirty days ago is gone.');
        self::assertSame(0, $this->rows(CheckTables::FINDING, 'finding_id', $findings[$ancient]));
        self::assertNotNull($this->runs()->get($earlier), 'An earlier run keeps its counts.');
        self::assertSame(1, $this->rows(CheckTables::FINDING, 'finding_id', $findings[$earlier]));
        self::assertSame(0, $this->rows(CheckTables::ITEM, 'finding_id', $findings[$earlier]));
        self::assertSame(2, $this->rows(CheckTables::ITEM, 'finding_id', $findings[$last]));
        self::assertSame(2, $this->rows(CheckTables::ITEM, 'finding_id', $findings[$pending]));
        self::assertSame(0, $this->rows(self::IDEMPOTENCY_TABLE, 'idempotency_key', 'rs-prune-old'));
        self::assertSame(1, $this->rows(self::IDEMPOTENCY_TABLE, 'idempotency_key', 'rs-prune-new'));
    }

    /**
     * A run that was asked for, ran and finished at a time
     *
     * @param int $at
     * @return int
     */
    private function finishedRun(int $at): int
    {
        $runs = $this->runs();
        $runId = $runs->request(CheckTrigger::Schedule, $at)->getId();
        self::assertTrue($runs->claim($runId, $at));
        $runs->start($runId, 2, 1, $at);
        $runs->finish($runId, $at + 60);

        return $runId;
    }

    /**
     * Seed a finding of a run with two pages behind it
     *
     * @param int $runId
     * @return int the finding's ID
     */
    private function findingWithPages(int $runId): int
    {
        $connection = $this->resource()->getConnection();
        $connection->insert($this->resource()->getTableName(CheckTables::FINDING), [
            'run_id' => $runId,
            'store_id' => 1,
            'finding_key' => 'merchant-listing-image',
            'rule_code' => 'merchant_listing',
            'path' => 'image',
            'sources' => '[]',
            'tone' => 'bad',
            'snippets' => '[]',
            'pages' => 2,
            'pages_by_kind' => '{"product":2}',
        ]);
        $findingId = $connection->fetchOne(
            $connection->select()
                ->from($this->resource()->getTableName(CheckTables::FINDING), ['finding_id'])
                ->where('run_id = ?', $runId)
        );
        self::assertIsNumeric($findingId);
        $findingId = (int) $findingId;
        foreach ([11, 12] as $entityId) {
            $connection->insert(
                $this->resource()->getTableName(CheckTables::ITEM),
                ['finding_id' => $findingId, 'kind' => 'product', 'entity_id' => $entityId]
            );
        }

        return $findingId;
    }

    /**
     * Seed an idempotency key created at a time
     *
     * @param string $key
     * @param int $at
     * @return void
     */
    private function idempotencyKey(string $key, int $at): void
    {
        $this->resource()->getConnection()->insert($this->resource()->getTableName(self::IDEMPOTENCY_TABLE), [
            'idempotency_key' => $key,
            'action' => 'check',
            'result' => '{}',
            'created_at' => gmdate('Y-m-d H:i:s', $at),
        ]);
    }

    /**
     * How many rows of a table have a value in a column
     *
     * @param string $table
     * @param string $column
     * @param int|string $value
     * @return int
     */
    private function rows(string $table, string $column, int|string $value): int
    {
        $connection = $this->resource()->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource()->getTableName($table), ['COUNT(*)'])
                ->where($column . ' = ?', $value)
        );
    }

    /**
     * @return CheckRunStoreInterface
     */
    private function runs(): CheckRunStoreInterface
    {
        return $this->get(CheckRunStoreInterface::class);
    }

    /**
     * @return ResourceConnection
     */
    private function resource(): ResourceConnection
    {
        return $this->get(ResourceConnection::class);
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
