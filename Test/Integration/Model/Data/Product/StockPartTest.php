<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Data\Product\StockPart;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\SkuKeyedStockStatus;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A product's availability is the stock status the storefront reads for the store view's website: the default
 * stock's, and a website's own stock's through the platform's stock status join, whatever the default stock says
 */
#[
    DbIsolation(true),
    DataFixture(
        ProductFixture::class,
        [
            'sku' => 'rs-availability-out',
            'extension_attributes' => ['stock_item' => ['qty' => 0, 'is_in_stock' => false]],
        ],
        as: 'out'
    ),
    DataFixture(ProductFixture::class, ['sku' => 'rs-availability-in'], as: 'in'),
]
class StockPartTest extends TestCase
{
    private const IN_STOCK = 'https://schema.org/InStock';
    private const OUT_OF_STOCK = 'https://schema.org/OutOfStock';

    public function testAvailabilityFollowsTheDefaultStock(): void
    {
        $part = Bootstrap::getObjectManager()->create(StockPart::class);
        self::assertInstanceOf(StockPart::class, $part);

        self::assertSame(
            [self::OUT_OF_STOCK, self::IN_STOCK],
            $this->availabilities($part, [$this->fixtureId('out'), $this->fixtureId('in')])
        );
    }

    public function testAProductTheWebsitesOwnStockCanSellIsInStockWhateverTheDefaultStockSays(): void
    {
        $part = $this->partSellingFrom(['rs-availability-out']);

        self::assertSame(
            [self::IN_STOCK, self::OUT_OF_STOCK],
            $this->availabilities($part, [$this->fixtureId('out'), $this->fixtureId('in')])
        );
    }

    public function testAProductMissingFromTheWebsitesStockIsOutOfStock(): void
    {
        $part = $this->partSellingFrom([]);

        self::assertSame(
            [self::OUT_OF_STOCK, self::OUT_OF_STOCK],
            $this->availabilities($part, [$this->fixtureId('out'), $this->fixtureId('in')])
        );
    }

    public function testAnIdNoProductHasGetsNoEntry(): void
    {
        $part = Bootstrap::getObjectManager()->create(StockPart::class);
        self::assertInstanceOf(StockPart::class, $part);

        self::assertSame([], $part->load([900000], $this->storeId(), [], []));
    }

    /**
     * The part reading the stock of a website that can sell only the given SKUs
     *
     * @param list<string> $salableSkus
     * @return StockPart
     */
    private function partSellingFrom(array $salableSkus): StockPart
    {
        $statuses = Bootstrap::getObjectManager()->create(SkuKeyedStockStatus::class);
        self::assertInstanceOf(SkuKeyedStockStatus::class, $statuses);
        $statuses->salableSkus = $salableSkus;
        $part = Bootstrap::getObjectManager()->create(StockPart::class, [
            'stock' => Bootstrap::getObjectManager()->create(StockStatusJoin::class, ['statuses' => $statuses]),
        ]);
        self::assertInstanceOf(StockPart::class, $part);

        return $part;
    }

    /**
     * The availability the part gives each product, in the order of the IDs
     *
     * @param StockPart $part
     * @param list<int> $ids
     * @return list<mixed>
     */
    private function availabilities(StockPart $part, array $ids): array
    {
        $entries = $part->load($ids, $this->storeId(), [], []);

        $availabilities = [];
        foreach ($ids as $id) {
            $entry = $entries[$id] ?? [];
            self::assertIsArray($entry);
            $availabilities[] = $entry['availability'] ?? null;
        }

        return $availabilities;
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
