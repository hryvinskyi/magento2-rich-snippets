<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Config;

use Hryvinskyi\RichSnippets\Model\Config\SavedScopeStores;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * A saved store view reaches itself, a saved website its own store views, the default scope every store view
 */
class SavedScopeStoresTest extends TestCase
{
    public function testTheDefaultScopeReachesEveryStoreView(): void
    {
        self::assertSame([1, 2, 3], $this->scopes()->storeIds('', ''));
    }

    public function testAWebsiteReachesOnlyItsStoreViews(): void
    {
        self::assertSame([2, 3], $this->scopes()->storeIds('2', ''));
        self::assertSame([1], $this->scopes()->storeIds('1', ''));
    }

    public function testAStoreViewReachesOnlyItself(): void
    {
        self::assertSame([3], $this->scopes()->storeIds('', '3'));
    }

    /**
     * Three store views: store view 1 in website 1, store views 3 and 2 in website 2
     *
     * @return SavedScopeStores
     */
    private function scopes(): SavedScopeStores
    {
        $stores = [];
        foreach ([1 => 1, 3 => 2, 2 => 2] as $storeId => $websiteId) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $store->method('getWebsiteId')->willReturn($websiteId);
            $stores[$storeId] = $store;
        }
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $storeManager->method('getStore')->willReturnCallback(
            static fn (string $id): StoreInterface => $stores[(int) $id]
        );
        $storeManager->method('getWebsite')->willReturnCallback(
            function (string $id): WebsiteInterface {
                $website = $this->createMock(WebsiteInterface::class);
                $website->method('getId')->willReturn((int) $id);

                return $website;
            }
        );

        return new SavedScopeStores($storeManager);
    }
}
