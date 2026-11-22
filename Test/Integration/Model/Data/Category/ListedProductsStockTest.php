<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Data\Category;

use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Data\Category\ListedProductsPart;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\SkuKeyedStockStatus;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The products a category page lists, as its snippet data reads them, agree with what the storefront shows: an
 * out-of-stock product is left out unless the store shows out-of-stock products, and the positions count only the
 * products listed. The stock is read through the platform's own stock status join, so a website selling from another
 * stock than the default one lists what that stock can sell.
 */
#[
    DbIsolation(true),
    DataFixture(CategoryFixture::class, ['name' => 'Stock RS'], as: 'category'),
    DataFixture(
        ProductFixture::class,
        [
            'sku' => 'rs-stock-out',
            'name' => 'Sold out RS',
            'category_ids' => ['$category.id$'],
            'extension_attributes' => ['stock_item' => ['qty' => 0, 'is_in_stock' => false]],
        ],
        as: 'out'
    ),
    DataFixture(
        ProductFixture::class,
        ['sku' => 'rs-stock-in', 'name' => 'On the shelf RS', 'category_ids' => ['$category.id$']],
        as: 'in'
    ),
]
class ListedProductsStockTest extends TestCase
{
    private const SOURCE = 'HryvinskyiRichSnippetsCategorySource';
    private const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';
    private const CATEGORY_PRODUCT_INDEXER = 'catalog_category_product';

    /**
     * The category product index holds the category's products, as the storefront reads them
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->get(IndexerRegistry::class)->get(self::CATEGORY_PRODUCT_INDEXER)->reindexRow(
            $this->fixtureId('category')
        );
    }

    #[Config(self::SHOW_OUT_OF_STOCK, 0, 'store', 'default')]
    public function testAnOutOfStockProductIsLeftOutWhenTheStoreHidesThem(): void
    {
        self::assertSame([[1, 'On the shelf RS']], $this->listed());
    }

    #[Config(self::SHOW_OUT_OF_STOCK, 1, 'store', 'default')]
    public function testAnOutOfStockProductIsListedFirstWhenTheStoreShowsThem(): void
    {
        self::assertSame([[1, 'Sold out RS'], [2, 'On the shelf RS']], $this->listed());
    }

    #[Config(self::SHOW_OUT_OF_STOCK, 0, 'store', 'default')]
    public function testAProductTheWebsitesOwnStockCannotSellIsLeftOutWhateverTheDefaultStockSays(): void
    {
        $statuses = Bootstrap::getObjectManager()->create(SkuKeyedStockStatus::class);
        self::assertInstanceOf(SkuKeyedStockStatus::class, $statuses);
        $statuses->salableSkus = ['rs-stock-out'];
        $part = Bootstrap::getObjectManager()->create(ListedProductsPart::class, [
            'stock' => Bootstrap::getObjectManager()->create(StockStatusJoin::class, ['statuses' => $statuses]),
        ]);
        self::assertInstanceOf(ListedProductsPart::class, $part);

        $entries = $part->load([$this->fixtureId('category')], $this->storeId(), ['category.products'], []);
        $listedEntries = $entries[$this->fixtureId('category')] ?? [];
        self::assertIsArray($listedEntries);

        $listed = [];
        foreach ($listedEntries as $entry) {
            self::assertIsArray($entry);
            $listed[] = [$entry['position'] ?? null, $entry['name'] ?? null];
        }
        self::assertSame([[1, 'Sold out RS']], $listed);
    }

    #[Config(self::SHOW_OUT_OF_STOCK, 0, 'store', 'default')]
    public function testTheListIsReadInOneQueryWithTheStockConditionApplied(): void
    {
        $source = $this->source();
        $connection = $this->get(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        self::assertInstanceOf(\Zend_Db_Profiler::class, $profiler);
        $source->reset();
        $source->prefetch([$this->fixtureId('category')], $this->storeId(), ['category.products'], []);
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $source->get($this->fixtureId('category'), $this->storeId(), 'products');
            $listing = array_filter(
                $profiler->getQueryProfiles() ?: [],
                static fn (mixed $query): bool => $query instanceof \Zend_Db_Profiler_Query
                    && str_contains($query->getQuery(), 'ROW_NUMBER() OVER')
            );
        } finally {
            $profiler->setEnabled(false);
            $source->reset();
        }

        self::assertCount(1, $listing);
    }

    /**
     * The position and name of each product the category's snippet data lists
     *
     * @return list<array{0: mixed, 1: mixed}>
     */
    private function listed(): array
    {
        $source = $this->source();
        $source->reset();
        $categoryId = $this->fixtureId('category');
        $source->prefetch([$categoryId], $this->storeId(), ['category.products'], []);
        $entries = $source->get($categoryId, $this->storeId(), 'products');
        $source->reset();
        self::assertIsArray($entries);

        $listed = [];
        foreach ($entries as $entry) {
            self::assertIsArray($entry);
            $listed[] = [$entry['position'] ?? null, $entry['name'] ?? null];
        }

        return $listed;
    }

    /**
     * @return EntityDataSourceInterface
     */
    private function source(): EntityDataSourceInterface
    {
        $source = Bootstrap::getObjectManager()->get(self::SOURCE);
        self::assertInstanceOf(EntityDataSourceInterface::class, $source);

        return $source;
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
    }

    /**
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function fixtureId(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
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
        $service = Bootstrap::getObjectManager()->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
