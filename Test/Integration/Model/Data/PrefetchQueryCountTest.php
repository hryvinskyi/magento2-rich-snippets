<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Review\Test\Fixture\Review as ReviewFixture;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Reading every part of the product source for a batch takes the same number of queries for 200 products as for a
 * handful: no query runs per product
 *
 * The queries are counted by the database connection's profiler. Both batches hold the same real products (a
 * simple product with a review in a category, and a configurable product), so every part takes the same paths;
 * the large batch is padded to 200 with IDs no product has. The price index is filled once a product's save
 * commits, so the test runs outside an isolating transaction; the fixtures remove themselves.
 */
#[
    DbIsolation(false),
    DataFixture(CategoryFixture::class, ['name' => 'Bags RS'], as: 'bags'),
    DataFixture(ProductFixture::class, ['category_ids' => ['$bags.id$']], as: 'simple'),
    DataFixture(ReviewFixture::class, ['entity_pk_value' => '$simple.id$']),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(ProductFixture::class, as: 'small'),
    DataFixture(ProductFixture::class, as: 'large'),
    DataFixture(
        ConfigurableProductFixture::class,
        ['_options' => ['$size$'], '_links' => ['$small$', '$large$']],
        as: 'configurable'
    ),
]
class PrefetchQueryCountTest extends TestCase
{
    private const SOURCE = 'HryvinskyiRichSnippetsProductSource';
    private const PARTS = [
        'attributes', 'price', 'special', 'stock', 'reviews', 'latest_reviews', 'gallery', 'url', 'trail', 'variants',
    ];
    private const PATHS = [
        'product.name', 'product.sku', 'product.color', 'product.image', 'product.short_description',
    ];

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
    }

    public function testAPrefetchOf200ProductsRunsAsManyQueriesAsOneOfAFew(): void
    {
        $real = [$this->id('simple'), $this->id('configurable')];
        $padding = range(900000, 900000 + 199 - count($real));

        $this->readAll($real);
        $few = $this->readAll($real);
        $many = $this->readAll([...$real, ...$padding]);

        self::assertSame(200, count([...$real, ...$padding]));
        self::assertGreaterThan(0, $few);
        self::assertSame($few, $many);
    }

    /**
     * Name the products to a fresh batch and read every part of each; the number of queries run
     *
     * @param list<int> $ids
     * @return int
     */
    private function readAll(array $ids): int
    {
        $source = $this->objectManager->get(self::SOURCE);
        self::assertInstanceOf(EntityDataSourceInterface::class, $source);
        $storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
        $source->reset();
        $connection = $this->get(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        self::assertInstanceOf(\Zend_Db_Profiler::class, $profiler);
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $source->prefetch($ids, $storeId, self::PATHS, ['reviews.latest' => 3]);
            foreach ($ids as $id) {
                foreach (self::PARTS as $part) {
                    $source->get($id, $storeId, $part);
                }
            }

            return $profiler->getTotalNumQueries();
        } finally {
            $profiler->setEnabled(false);
            $source->reset();
        }
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

        return $this->entityId($fixture);
    }

    /**
     * The ID of a fixture's entity
     *
     * @param DataObject $fixture
     * @return int
     */
    private function entityId(DataObject $fixture): int
    {
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
