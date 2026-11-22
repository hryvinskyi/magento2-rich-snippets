<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultWriterInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\ChunkCheck;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\Check\StoreTallyFactory;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A batch of the catalog check reads what its pages need before it looks at the first one, and writes what it found
 * in one go: checking and recording six product pages runs as many queries as two
 *
 * With the module's cache type on, as the module's installation leaves it, the list of snippets a store view selects
 * from is read from it, not from the database, once it was built. With the cache type off, the run reads that list
 * from the database once per store view, not once per page.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(VirtualProductFixture::class, ['short_description' => 'One.'], as: 'p1'),
    DataFixture(VirtualProductFixture::class, as: 'p2'),
    DataFixture(VirtualProductFixture::class, ['short_description' => 'Three.'], as: 'p3'),
    DataFixture(VirtualProductFixture::class, as: 'p4'),
    DataFixture(VirtualProductFixture::class, ['short_description' => 'Five.'], as: 'p5'),
    DataFixture(VirtualProductFixture::class, as: 'p6'),
]
class ChunkQueryCountTest extends TestCase
{
    use StoresTestSnippets;

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
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    #[Cache('rich_snippets', true)]
    public function testCheckingSixPagesRunsAsManyQueriesAsTwo(): void
    {
        $this->assertSixPagesRunAsManyQueriesAsTwo();
    }

    #[Cache('rich_snippets', false)]
    public function testWithTheCacheTypeOffSixPagesStillRunAsManyQueriesAsTwo(): void
    {
        $this->assertSixPagesRunAsManyQueriesAsTwo();
    }

    /**
     * Check two product pages, then six, after a first batch that read what every batch reads once
     *
     * @return void
     */
    private function assertSixPagesRunAsManyQueriesAsTwo(): void
    {
        $this->storeSnippet('rs-chunk-product', 'Chunk product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'image' => '{{product.image}}',
            'description' => '{{product.short_description}}',
            'sku' => '{{product.sku}}',
            'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'EUR'],
        ], ['kinds' => ['product'], 'conditions' => [['product_type', '==', 'virtual']], 'active' => true]);
        $this->get(RichSnippetsCache::class)->clean();
        $runId = $this->get(CheckRunStoreInterface::class)->request(CheckTrigger::Manual, time())->getId();
        $all = array_map(fn (string $name): int => $this->id($name), ['p1', 'p2', 'p3', 'p4', 'p5', 'p6']);

        $this->queriesFor($runId, $all);
        $two = $this->queriesFor($runId, array_slice($all, 0, 2));
        $six = $this->queriesFor($runId, $all);

        self::assertGreaterThan(0, $two);
        self::assertSame($two, $six);
    }

    /**
     * Check a batch of product pages and record what was found; the number of queries it ran
     *
     * @param int $runId
     * @param list<int> $ids
     * @return int
     */
    private function queriesFor(int $runId, array $ids): int
    {
        $storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
        $tally = $this->get(StoreTallyFactory::class)->create(['storeId' => $storeId]);
        $pages = $this->get(PageEntityFactoryInterface::class);
        $batch = array_map(fn (int $id) => $pages->create('product', $id, $storeId), $ids);
        $chunks = $this->get(ChunkCheck::class);
        $results = $this->get(CheckResultWriterInterface::class);
        $connection = $this->get(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        self::assertInstanceOf(\Zend_Db_Profiler::class, $profiler);

        return (int) $this->get(FrontendEmulationInterface::class)->run(
            $storeId,
            static function () use ($profiler, $chunks, $results, $tally, $batch, $storeId, $runId): int {
                $profiler->setEnabled(true);
                $profiler->clear();
                try {
                    $chunks->check('product', $storeId, $batch, $tally);
                    $results->writeFindings($runId, $tally);

                    return $profiler->getTotalNumQueries();
                } finally {
                    $profiler->setEnabled(false);
                }
            }
        );
    }

    /**
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(DataObject::class, $fixture);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
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
