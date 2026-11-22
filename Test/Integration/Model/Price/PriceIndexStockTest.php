<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Price;

use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Price\PriceIndex;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\SkuKeyedStockStatus;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The children of a configurable product carry the stock status the storefront reads for the store view's website:
 * the default stock's, and a website's own stock's through the platform's stock status join, which decides which
 * variants a snippet offers
 *
 * The price index is filled once a product's save commits, so the test runs outside an isolating transaction; the
 * fixtures remove themselves. Both children are indexed in stock, since the price index leaves out a product saved
 * out of stock while the store hides such products; a test that needs one out of stock in the default stock marks it
 * so in the stock status index afterwards, and puts it back before the fixtures are removed.
 */
#[
    AppArea('frontend'),
    DbIsolation(false),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(ProductFixture::class, ['sku' => 'rs-stock-weekender-s', 'price' => 310], as: 'small'),
    DataFixture(ProductFixture::class, ['sku' => 'rs-stock-weekender-l', 'price' => 290], as: 'large'),
    DataFixture(
        ConfigurableProductFixture::class,
        ['sku' => 'rs-stock-weekender', '_options' => ['$size$'], '_links' => ['$small$', '$large$']],
        as: 'weekender'
    ),
]
class PriceIndexStockTest extends TestCase
{
    public function testEachChildCarriesTheDefaultStocksStatus(): void
    {
        $index = Bootstrap::getObjectManager()->get(PriceIndex::class);
        self::assertInstanceOf(PriceIndex::class, $index);
        $this->markInDefaultStock('large', 0);
        try {
            $inStock = $this->inStock($index);
        } finally {
            $this->markInDefaultStock('large', 1);
        }

        self::assertSame(['rs-stock-weekender-l' => false, 'rs-stock-weekender-s' => true], $inStock);
    }

    public function testEachChildCarriesTheStatusOfTheWebsitesOwnStockWhateverTheDefaultStockSays(): void
    {
        $statuses = Bootstrap::getObjectManager()->create(SkuKeyedStockStatus::class);
        self::assertInstanceOf(SkuKeyedStockStatus::class, $statuses);
        $statuses->salableSkus = ['rs-stock-weekender-l'];
        $index = Bootstrap::getObjectManager()->create(PriceIndex::class, [
            'stock' => Bootstrap::getObjectManager()->create(StockStatusJoin::class, ['statuses' => $statuses]),
        ]);
        self::assertInstanceOf(PriceIndex::class, $index);

        self::assertSame(
            ['rs-stock-weekender-l' => true, 'rs-stock-weekender-s' => false],
            $this->inStock($index)
        );
    }

    /**
     * Whether each child of the configurable fixture is in stock, by SKU
     *
     * @param PriceIndex $index
     * @return array<string, bool>
     */
    private function inStock(PriceIndex $index): array
    {
        $parentId = $this->fixtureId('weekender');
        $inStock = [];
        foreach ($index->children([$parentId], $this->storeId())[$parentId] ?? [] as $child) {
            $inStock[$child['sku']] = $child['in_stock'];
        }
        ksort($inStock);

        return $inStock;
    }

    /**
     * Set a fixture's status in the default stock's status index, which the test commits
     *
     * @param string $name
     * @param int $status 1 for in stock, 0 for out of stock
     * @return void
     */
    private function markInDefaultStock(string $name, int $status): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $resource->getConnection()->update(
            $resource->getTableName('cataloginventory_stock_status'),
            ['stock_status' => $status],
            ['product_id = ?' => $this->fixtureId($name)]
        );
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);

        return (int) $storeManager->getDefaultStoreView()?->getId();
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
}
